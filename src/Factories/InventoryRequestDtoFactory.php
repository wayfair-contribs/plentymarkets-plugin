<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Wayfair\Core\Dto\Inventory\RequestDTO;

class InventoryRequestDtoFactory
{

    public function create(array $fromData = null): RequestDTO
    {
        /** @var RequestDTO */
        $instance = pluginApp(RequestDTO::class);

        if (isset($fromData) && is_array($fromData)) {
            $instance->adoptArray($fromData);
        }
        return $instance;
    }
}
