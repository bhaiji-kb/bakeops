<?php

namespace App\Services\ChannelIntegrations\Data;

class NormalizedChannelWebhook
{
    public function __construct(
        public readonly string $externalOrderId,
        public readonly ?string $externalEventId = null,
        public readonly ?string $eventType = null,
        public readonly string $status = 'received',
        public readonly ?float $totalAmount = null,
        public readonly ?string $customerName = null,
        public readonly ?string $customerIdentifier = null,
        public readonly array $normalizedPayload = [],
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $occurredAt = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'external_order_id' => $this->externalOrderId,
            'external_event_id' => $this->externalEventId,
            'event_type' => $this->eventType,
            'status' => $this->status,
            'total_amount' => $this->totalAmount,
            'customer_name' => $this->customerName,
            'customer_identifier' => $this->customerIdentifier,
            'normalized_payload' => $this->normalizedPayload,
            'idempotency_key' => $this->idempotencyKey,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
