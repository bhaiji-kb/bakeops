<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChannelOrderEvent;
use App\Models\ChannelOrder;
use App\Models\ChannelOrderEvent;
use App\Models\IntegrationConnector;
use App\Services\ChannelIntegrations\ChannelConnectorRegistry;
use App\Services\CustomerMasterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChannelWebhookController extends Controller
{
    public function receive(Request $request, string $channel): JsonResponse
    {
        $channelCode = strtolower(trim($channel));
        $connector = IntegrationConnector::query()
            ->where('code', $channelCode)
            ->where('is_active', true)
            ->first();

        if (!$connector) {
            return response()->json([
                'message' => 'Connector not found or inactive.',
            ], 404);
        }

        $adapter = app(ChannelConnectorRegistry::class)->resolve($connector);
        $signatureValid = $adapter->verifyWebhook($request, $connector);
        $normalized = $adapter->normalizeWebhook($request, $connector);

        if (trim($normalized->externalOrderId) === '') {
            return response()->json([
                'message' => 'external_order_id is required in webhook payload.',
            ], 422);
        }

        $idempotencyKey = $this->buildIdempotencyKey(
            channelCode: $channelCode,
            externalOrderId: $normalized->externalOrderId,
            preferredKey: (string) ($normalized->idempotencyKey ?? ''),
            rawPayload: (string) $request->getContent()
        );

        $event = ChannelOrderEvent::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($event) {
            return response()->json([
                'message' => 'Webhook already received.',
                'duplicate' => true,
                'event_id' => (int) $event->id,
                'order_id' => (int) ($event->channel_order_id ?? 0),
            ]);
        }

        $customerMaster = app(CustomerMasterService::class);
        $channelOrderId = DB::transaction(function () use ($connector, $channelCode, $normalized, $idempotencyKey, $signatureValid, $request, $customerMaster) {
            $status = strtolower(trim($normalized->status));
            if ($status === '') {
                $status = 'received';
            }

            $statusTimestamps = $this->statusTimestampUpdates($status, $normalized->occurredAt);
            $payload = $request->json()->all() ?: $request->all();
            $profile = $this->extractCustomerProfile(
                $payload,
                is_array($normalized->normalizedPayload) ? $normalized->normalizedPayload : []
            );
            $customer = $customerMaster->upsertByIdentifier(
                (string) ($normalized->customerIdentifier ?? ''),
                (string) ($normalized->customerName ?? ''),
                $profile
            );
            $normalizedIdentifier = $customerMaster->normalizeIdentifier((string) ($normalized->customerIdentifier ?? ''));
            $customerName = $customer?->name ?: $customerMaster->normalizeName((string) ($normalized->customerName ?? ''));
            $customerIdentifier = $normalizedIdentifier !== ''
                ? $normalizedIdentifier
                : (string) ($customer?->mobile ?: $customer?->identifier ?: '');

            $channelOrder = ChannelOrder::query()->updateOrCreate(
                [
                    'channel' => $channelCode,
                    'external_order_id' => $normalized->externalOrderId,
                ],
                array_merge([
                    'integration_connector_id' => $connector->id,
                    'customer_name' => $customerName !== '' ? $customerName : null,
                    'customer_identifier' => $customerIdentifier !== '' ? $customerIdentifier : null,
                    'order_total' => $normalized->totalAmount,
                    'status' => $status,
                    'last_event_at' => $normalized->occurredAt ?: now()->toDateTimeString(),
                    'latest_payload' => $payload,
                    'normalized_payload' => $normalized->normalizedPayload,
                ], $statusTimestamps)
            );

            $event = ChannelOrderEvent::create([
                'channel_order_id' => $channelOrder->id,
                'integration_connector_id' => $connector->id,
                'channel' => $channelCode,
                'external_order_id' => $normalized->externalOrderId,
                'external_event_id' => $normalized->externalEventId,
                'idempotency_key' => $idempotencyKey,
                'event_type' => $normalized->eventType,
                'signature_valid' => $signatureValid,
                'payload' => $payload,
                'normalized_payload' => $normalized->toArray(),
                'process_status' => 'queued',
                'retry_count' => 0,
            ]);

            ProcessChannelOrderEvent::dispatch((int) $event->id);

            return (int) $channelOrder->id;
        });

        return response()->json([
            'message' => 'Webhook accepted.',
            'duplicate' => false,
            'channel_order_id' => $channelOrderId,
        ], 202);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $normalizedPayload
     */
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $normalizedPayload
     * @return array<string, string>
     */
    private function extractCustomerProfile(array $payload, array $normalizedPayload): array
    {
        $customerMaster = app(CustomerMasterService::class);

        $address = $this->extractFirstValue($normalizedPayload, $payload, [
            'customer_address',
            'delivery_address',
            'customer.address',
            'customer.location.address',
            'customer.location.full_address',
        ]);
        $addressLine1 = $this->extractFirstValue($normalizedPayload, $payload, [
            'customer.address_line1',
            'customer.apartment_house',
            'customer.house',
        ]);
        $road = $this->extractFirstValue($normalizedPayload, $payload, [
            'customer.road',
            'customer.street',
        ]);
        $sector = $this->extractFirstValue($normalizedPayload, $payload, [
            'customer.sector',
            'customer.area',
        ]);
        $city = $this->extractFirstValue($normalizedPayload, $payload, [
            'customer.city',
            'customer.location.city',
        ]);
        $pincode = $this->extractFirstValue($normalizedPayload, $payload, [
            'customer.pincode',
            'customer.postal_code',
            'customer.zipcode',
        ]);
        $preference = $this->extractFirstValue($normalizedPayload, $payload, [
            'customer.preference',
            'customer.notes',
        ]);

        return $customerMaster->normalizeProfile([
            'address' => $address,
            'address_line1' => $addressLine1,
            'road' => $road,
            'sector' => $sector,
            'city' => $city,
            'pincode' => $pincode,
            'preference' => $preference,
        ]);
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $paths
     */
    private function extractFirstValue(array $normalizedPayload, array $payload, array $paths): string
    {
        foreach ([$normalizedPayload, $payload] as $source) {
            foreach ($paths as $path) {
                $value = $this->getNestedValue($source, $path);
                if (!is_scalar($value)) {
                    continue;
                }

                $text = trim((string) $value);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    private function buildIdempotencyKey(
        string $channelCode,
        string $externalOrderId,
        string $preferredKey,
        string $rawPayload
    ): string {
        $preferredKey = trim($preferredKey);
        if ($preferredKey !== '') {
            return strtolower($channelCode . ':' . $preferredKey);
        }

        return strtolower($channelCode . ':' . $externalOrderId . ':' . hash('sha256', $rawPayload));
    }

    /**
     * @return array<string, string>
     */
    private function statusTimestampUpdates(string $status, ?string $occurredAt): array
    {
        $timestamp = $occurredAt ?: now()->toDateTimeString();

        return match ($status) {
            'accepted', 'acknowledged' => ['accepted_at' => $timestamp],
            'ready', 'prepared' => ['ready_at' => $timestamp],
            'delivered', 'completed' => ['delivered_at' => $timestamp],
            'cancelled', 'rejected' => ['cancelled_at' => $timestamp],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function getNestedValue(array $payload, string $path): mixed
    {
        if (!str_contains($path, '.')) {
            return $payload[$path] ?? null;
        }

        $segments = explode('.', $path);
        $value = $payload;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
