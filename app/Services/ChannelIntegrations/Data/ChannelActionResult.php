<?php

namespace App\Services\ChannelIntegrations\Data;

class ChannelActionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly array $meta = [],
    ) {
    }
}
