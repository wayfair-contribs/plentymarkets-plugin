<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'AbstractMockFactory.php');

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\PlentyMockets\AbstractMockFactory;

class MockVariationSearchRepositoryFactory extends AbstractMockFactory
{
    public function create(array $variationDataArraysForPages, array $expectedFilters = null, int $searchesExpected = null): VariationSearchRepositoryContract
    {
        /** @var VariationSearchRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $variationSearchRepository  = $this->createMock(VariationSearchRepositoryContract::class);

        $this->configureRepository('search', $variationSearchRepository, $variationDataArraysForPages, $expectedFilters, $searchesExpected);

        return $variationSearchRepository;
    }
}
