<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Wayfair\Core\Dto\Inventory\ResponseDTO;

class InventoryResponseDtoFactory
{

    public function create(array $fromData = null): ResponseDTO
    {
        /** @var ResponseDTO */
        $instance = pluginApp(ResponseDTO::class);

        if (isset($fromData) && is_array($fromData)) {
            $instance->adoptArray($fromData);
        }
        return $instance;
    }
}
