<?php

namespace App\Services\ChannelIntegrations;

use App\Models\IntegrationConnector;
use App\Services\ChannelIntegrations\Connectors\GenericConnector;
use App\Services\ChannelIntegrations\Connectors\SwiggyConnector;
use App\Services\ChannelIntegrations\Connectors\ZomatoConnector;
use App\Services\ChannelIntegrations\Contracts\ChannelConnector;

class ChannelConnectorRegistry
{
    public function resolve(IntegrationConnector $connector): ChannelConnector
    {
        return match (strtolower((string) $connector->driver)) {
            IntegrationConnector::DRIVER_ZOMATO => app(ZomatoConnector::class),
            IntegrationConnector::DRIVER_SWIGGY => app(SwiggyConnector::class),
            default => app(GenericConnector::class),
        };
    }
}
