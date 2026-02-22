<?php

namespace App\Services\ChannelIntegrations\Connectors;

use App\Models\IntegrationConnector;

class ZomatoConnector extends GenericConnector
{
    public function driver(): string
    {
        return IntegrationConnector::DRIVER_ZOMATO;
    }
}
