<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;

class VariationSearchRepositoryFactory
{

    public function create(): VariationSearchRepositoryContract
    {
        /** @var VariationSearchRepositoryContract */
        $instance = pluginApp(VariationSearchRepositoryContract::class);
        return $instance;
    }
}
