<?php

namespace App\Services\ChannelIntegrations\Connectors;

use App\Models\ChannelOrder;
use App\Models\IntegrationConnector;
use App\Services\ChannelIntegrations\Contracts\ChannelConnector;
use App\Services\ChannelIntegrations\Data\ChannelActionResult;
use App\Services\ChannelIntegrations\Data\NormalizedChannelWebhook;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenericConnector implements ChannelConnector
{
    public function driver(): string
    {
        return IntegrationConnector::DRIVER_GENERIC;
    }

    public function verifyWebhook(Request $request, IntegrationConnector $connector): bool
    {
        $secret = trim((string) ($connector->webhook_secret ?? ''));
        if ($secret === '') {
            return true;
        }

        $payload = (string) $request->getContent();
        $signature = (string) ($request->header('X-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? '');

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        $candidate = strtolower(trim(str_replace('sha256=', '', $signature)));

        return hash_equals($expected, $candidate);
    }

    public function normalizeWebhook(Request $request, IntegrationConnector $connector): NormalizedChannelWebhook
    {
        $payload = $request->json()->all();
        if (!is_array($payload) || empty($payload)) {
            $payload = $request->all();
        }

        $externalOrderId = $this->extractString($payload, [
            'order_id',
            'orderId',
            'external_order_id',
            'order.reference',
            'order.id',
        ]);

        $eventId = $this->extractString($payload, [
            'event_id',
            'eventId',
            'webhook_id',
        ]);

        $eventType = $this->extractString($payload, [
            'event_type',
            'eventType',
            'type',
            'status_event',
        ]);

        $status = strtolower($this->extractString($payload, [
            'status',
            'order_status',
            'state',
        ]) ?: 'received');

        $customerName = $this->extractString($payload, [
            'customer_name',
            'customer.name',
            'customer.full_name',
        ]);

        $customerIdentifier = $this->extractString($payload, [
            'customer_mobile',
            'customer.phone',
            'customer.mobile',
            'customer_identifier',
        ]);

        $totalAmountRaw = $this->extractString($payload, [
            'total_amount',
            'order_total',
            'amount',
            'bill_amount',
        ]);
        $totalAmount = is_numeric($totalAmountRaw) ? round((float) $totalAmountRaw, 2) : null;

        $occurredAt = $this->extractString($payload, [
            'occurred_at',
            'event_time',
            'created_at',
            'timestamp',
        ]);

        $idempotencyKey = $this->extractString($payload, [
            'idempotency_key',
            'request_id',
            'event_id',
        ]) ?: (string) ($request->header('X-Idempotency-Key') ?? $request->header('X-Request-Id') ?? '');

        return new NormalizedChannelWebhook(
            externalOrderId: $externalOrderId,
            externalEventId: $eventId !== '' ? $eventId : null,
            eventType: $eventType !== '' ? $eventType : null,
            status: $status !== '' ? $status : 'received',
            totalAmount: $totalAmount,
            customerName: $customerName !== '' ? $customerName : null,
            customerIdentifier: $customerIdentifier !== '' ? $customerIdentifier : null,
            normalizedPayload: $payload,
            idempotencyKey: $idempotencyKey !== '' ? $idempotencyKey : null,
            occurredAt: $occurredAt !== '' ? $occurredAt : null,
        );
    }

    public function acceptOrder(ChannelOrder $order, IntegrationConnector $connector): ChannelActionResult
    {
        return $this->performAction($order, $connector, 'accept', []);
    }

    public function rejectOrder(ChannelOrder $order, IntegrationConnector $connector, string $reason): ChannelActionResult
    {
        return $this->performAction($order, $connector, 'reject', [
            'reason' => $reason,
        ]);
    }

    public function markOrderReady(ChannelOrder $order, IntegrationConnector $connector): ChannelActionResult
    {
        return $this->performAction($order, $connector, 'ready', []);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $candidates
     */
    protected function extractString(array $payload, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = $this->getNestedValue($payload, $candidate);
            if ($value === null) {
                continue;
            }

            if (is_scalar($value)) {
                $string = trim((string) $value);
                if ($string !== '') {
                    return $string;
                }
            }
        }

        return '';
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

    private function performAction(
        ChannelOrder $order,
        IntegrationConnector $connector,
        string $action,
        array $runtimeValues
    ): ChannelActionResult {
        $baseUrl = rtrim((string) ($connector->api_base_url ?? ''), '/');
        if ($baseUrl === '') {
            return new ChannelActionResult(
                success: false,
                message: 'Connector base URL is missing.',
                meta: ['action' => $action]
            );
        }

        $settings = is_array($connector->settings) ? $connector->settings : [];
        $actionConfig = $this->resolveActionConfig($settings, $action);
        $placeholders = array_merge($runtimeValues, [
            'external_order_id' => (string) $order->external_order_id,
            'channel' => (string) $order->channel,
            'status' => (string) $order->status,
        ]);

        $method = strtoupper((string) ($actionConfig['method'] ?? 'POST'));
        $path = $this->replacePlaceholders((string) ($actionConfig['path'] ?? '/orders/{external_order_id}/' . $action), $placeholders);
        $requestUrl = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : $baseUrl . '/' . ltrim($path, '/');

        $headers = $this->replacePlaceholdersRecursive(
            is_array($actionConfig['headers'] ?? null) ? $actionConfig['headers'] : [],
            $placeholders
        );
        $body = $this->replacePlaceholdersRecursive(
            is_array($actionConfig['body'] ?? null) ? $actionConfig['body'] : [],
            $placeholders
        );

        $timeoutSeconds = max(3, (int) ($settings['timeout_seconds'] ?? 12));
        $request = $this->buildHttpRequest($connector, $settings, $timeoutSeconds)->withHeaders($headers);

        try {
            $response = match ($method) {
                'GET' => $request->get($requestUrl, $body),
                'PUT' => $request->put($requestUrl, $body),
                'PATCH' => $request->patch($requestUrl, $body),
                'DELETE' => $request->delete($requestUrl, $body),
                default => $request->post($requestUrl, $body),
            };
        } catch (Throwable $e) {
            return new ChannelActionResult(
                success: false,
                message: 'Connector call failed: ' . $e->getMessage(),
                meta: [
                    'action' => $action,
                    'method' => $method,
                    'url' => $requestUrl,
                ]
            );
        }

        if (!$response->successful()) {
            return new ChannelActionResult(
                success: false,
                message: 'Connector rejected action. HTTP ' . $response->status() . '.',
                meta: [
                    'action' => $action,
                    'method' => $method,
                    'url' => $requestUrl,
                    'http_status' => $response->status(),
                    'response_body' => mb_substr($response->body(), 0, 1000),
                ]
            );
        }

        return new ChannelActionResult(
            success: true,
            message: ucfirst($action) . ' acknowledged by connector.',
            meta: [
                'action' => $action,
                'method' => $method,
                'url' => $requestUrl,
                'http_status' => $response->status(),
                'response_json' => $response->json(),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function resolveActionConfig(array $settings, string $action): array
    {
        $actions = is_array($settings['actions'] ?? null) ? $settings['actions'] : [];
        $config = is_array($actions[$action] ?? null) ? $actions[$action] : [];

        $defaultPath = '/orders/{external_order_id}/' . $action;
        $pathFallbackKey = $action . '_path';
        $methodFallbackKey = $action . '_method';
        $bodyFallbackKey = $action . '_body';
        $headersFallbackKey = $action . '_headers';

        return [
            'path' => $config['path']
                ?? $settings[$pathFallbackKey]
                ?? $defaultPath,
            'method' => $config['method']
                ?? $settings[$methodFallbackKey]
                ?? 'POST',
            'body' => $config['body']
                ?? $settings[$bodyFallbackKey]
                ?? [],
            'headers' => $config['headers']
                ?? $settings[$headersFallbackKey]
                ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function buildHttpRequest(IntegrationConnector $connector, array $settings, int $timeoutSeconds): PendingRequest
    {
        $request = Http::timeout($timeoutSeconds)->acceptJson()->asJson();

        $auth = is_array($settings['auth'] ?? null) ? $settings['auth'] : [];
        $authType = strtolower((string) ($auth['type'] ?? ''));
        $apiKey = (string) ($connector->api_key ?? '');
        $apiSecret = (string) ($connector->api_secret ?? '');

        if ($authType === 'bearer') {
            if ($apiKey !== '') {
                return $request->withToken($apiKey);
            }

            return $request;
        }

        if ($authType === 'basic') {
            return $request->withBasicAuth($apiKey, $apiSecret);
        }

        if ($authType === 'header') {
            $headerName = (string) ($auth['header_name'] ?? 'X-API-Key');
            $headerValue = (string) ($auth['header_value'] ?? $apiKey);

            if ($headerName !== '' && $headerValue !== '') {
                $request = $request->withHeaders([$headerName => $headerValue]);
            }

            return $request;
        }

        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }
        if ($apiSecret !== '') {
            $request = $request->withHeaders([
                'X-API-Secret' => $apiSecret,
            ]);
        }

        return $request;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function replacePlaceholders(string $subject, array $values): string
    {
        foreach ($values as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $subject = str_replace(
                '{' . $key . '}',
                (string) ($value ?? ''),
                $subject
            );
        }

        return $subject;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function replacePlaceholdersRecursive(mixed $value, array $values): mixed
    {
        if (is_string($value)) {
            return $this->replacePlaceholders($value, $values);
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->replacePlaceholdersRecursive($item, $values);
        }

        return $result;
    }
}
