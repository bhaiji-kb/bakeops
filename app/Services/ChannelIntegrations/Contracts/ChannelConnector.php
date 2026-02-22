<?php

namespace App\Services\ChannelIntegrations\Contracts;

use App\Models\ChannelOrder;
use App\Models\IntegrationConnector;
use App\Services\ChannelIntegrations\Data\ChannelActionResult;
use App\Services\ChannelIntegrations\Data\NormalizedChannelWebhook;
use Illuminate\Http\Request;

interface ChannelConnector
{
    public function driver(): string;

    public function verifyWebhook(Request $request, IntegrationConnector $connector): bool;

    public function normalizeWebhook(Request $request, IntegrationConnector $connector): NormalizedChannelWebhook;

    public function acceptOrder(ChannelOrder $order, IntegrationConnector $connector): ChannelActionResult;

    public function rejectOrder(ChannelOrder $order, IntegrationConnector $connector, string $reason): ChannelActionResult;

    public function markOrderReady(ChannelOrder $order, IntegrationConnector $connector): ChannelActionResult;
}
