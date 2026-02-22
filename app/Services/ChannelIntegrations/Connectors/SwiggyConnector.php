<?php

namespace App\Services\ChannelIntegrations\Connectors;

use App\Models\IntegrationConnector;

class SwiggyConnector extends GenericConnector
{
    public function driver(): string
    {
        return IntegrationConnector::DRIVER_SWIGGY;
    }
}
