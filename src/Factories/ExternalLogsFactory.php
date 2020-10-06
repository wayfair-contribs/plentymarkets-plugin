<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Wayfair\Models\ExternalLogs;

class ExternalLogsFactory
{

    public function create(): ExternalLogs
    {
        /** @var ExternalLogs */
        $instance = pluginApp(ExternalLogs::class);
        return $instance;
    }
}
