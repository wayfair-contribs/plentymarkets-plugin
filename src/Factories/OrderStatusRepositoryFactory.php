<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Factories;

use Plenty\Modules\Order\Status\Contracts\OrderStatusRepositoryContract;

class OrderStatusRepositoryFactory
{

    public function create(): OrderStatusRepositoryContract
    {
        /** @var OrderStatusRepositoryContract */
        $instance = pluginApp(OrderStatusRepositoryContract::class);
        return $instance;
    }
}
