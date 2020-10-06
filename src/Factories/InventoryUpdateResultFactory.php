<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Wayfair\Models\InventoryUpdateResult;

class InventoryUpdateResultFactory
{

    public function create(array $fromData = null): InventoryUpdateResult
    {
        /** @var InventoryUpdateResult */
        $instance = pluginApp(InventoryUpdateResult::class);

        if (isset($fromData) && is_array($fromData))
        {
            $instance->adoptArray($fromData);
        }
        return $instance;
    }
}
