<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;

class StockRepositoryFactory
{

    public function create(): StockRepositoryContract
    {
        /** @var StockRepositoryContract */
        $instance = pluginApp(StockRepositoryContract::class);
        return $instance;
    }
}
