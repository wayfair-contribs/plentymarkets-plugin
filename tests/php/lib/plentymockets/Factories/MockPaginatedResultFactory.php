<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

use Plenty\Repositories\Models\PaginatedResult;
use Wayfair\PlentyMockets\AbstractMockFactory;

class MockPaginatedResultFactory extends AbstractMockFactory
{
    private $pageNumber = 1;

    public function createNext(array $searchResults, bool $isLastPage): PaginatedResult
    {
        /** @var PaginatedResult&\PHPUnit\Framework\MockObject\MockObject */
        $paginatedResult  = $this->createMock(PaginatedResult::class);

        $curPageNumber = $this->pageNumber++;

        $paginatedResult->method('getResult')->willReturn($searchResults);

        $paginatedResult->method('getPage')->willReturn($curPageNumber);

        $paginatedResult->method('getCurrentPage')->willReturn($curPageNumber);

        $paginatedResult->method('isLastPage')->willReturn($isLastPage);
        if ($isLastPage)
        {
            $this->pageNumber = 1;
        }

        return $paginatedResult;
    }
}
