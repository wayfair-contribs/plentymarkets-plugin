<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Plenty\Modules\Item\VariationStock\Contracts\VariationStockRepositoryContract;

class VariationStockRepositoryFactory
{

    public function create(): VariationStockRepositoryContract
    {
        /** @var VariationStockRepositoryContract */
        $instance = pluginApp(VariationStockRepositoryContract::class);
        return $instance;
    }
}
