<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'AbstractMockFactory.php');


use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Wayfair\PlentyMockets\AbstractMockFactory;

class MockOrderRepositoryFactory extends AbstractMockFactory
{
    public function create(array $stockDataArraysForPages, array $expectedFilters = null, int $searchesExpected = null): OrderRepositoryContract
    {
        /** @var OrderRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $stockRepository  = $this->createMock(OrderRepositoryContract::class);

        $this->configureRepository('searchOrders', $stockRepository, $stockDataArraysForPages, $expectedFilters, $searchesExpected);

        return $stockRepository;
    }
}
