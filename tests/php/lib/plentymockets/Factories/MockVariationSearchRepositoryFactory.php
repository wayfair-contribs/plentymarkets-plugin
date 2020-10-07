<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'AbstractMockFactory.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'MockPaginatedResultFactory.php');

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\PlentyMockets\AbstractMockFactory;

class MockVariationSearchRepositoryFactory extends AbstractMockFactory
{
    public function create(array $cannedVariationDataArrays): VariationSearchRepositoryContract
    {
        $pageFactory = new MockPaginatedResultFactory($this->getTestCase());

        /** @var VariationSearchRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $variationSearchRepository  = $this->createMock(VariationSearchRepositoryContract::class);

        $pages = [];
        $amtPages = count($cannedVariationDataArrays);

        $page = 0;
        while ($page++ < $amtPages - 1) {
            $pages[] = $pageFactory->create($cannedVariationDataArrays[$page], false);
        }

        $dataForLastPage = [];

        // put in the last page
        if ($amtPages > 0) {
            $dataForLastPage = $cannedVariationDataArrays[$amtPages - 1];
        }

        // there's always at least one page returned by Plenty, even if there are no results.
        // the last page MUST report that it is the last page, or there will be infinite looping!
        $pages[] = $pageFactory->create($dataForLastPage, true);

        $variationSearchRepository->method('search')->willReturnOnConsecutiveCalls(...$pages);

        return $variationSearchRepository;
    }
}
