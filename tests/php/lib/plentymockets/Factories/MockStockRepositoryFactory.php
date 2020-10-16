<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'AbstractMockFactory.php');

use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;
use Wayfair\PlentyMockets\AbstractMockFactory;

class MockStockRepositoryFactory extends AbstractMockFactory
{
    public function create(array $stockDataArraysForPages, array $expectedFilters = null, int $searchesExpected = null): StockRepositoryContract
    {
        /** @var StockRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $stockRepository  = $this->createMock(StockRepositoryContract::class);

        $this->configureRepository('listStock', $stockRepository, $stockDataArraysForPages, $expectedFilters, $searchesExpected);

        return $stockRepository;
    }
}
