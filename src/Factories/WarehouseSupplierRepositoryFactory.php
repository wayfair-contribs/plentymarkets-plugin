<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Wayfair\Repositories\WarehouseSupplierRepository;

class WarehouseSupplierRepositoryFactory
{

    public function create(): WarehouseSupplierRepository
    {
        /** @var WarehouseSupplierRepository */
        $instance = pluginApp(WarehouseSupplierRepository::class);
        return $instance;
    }
}
