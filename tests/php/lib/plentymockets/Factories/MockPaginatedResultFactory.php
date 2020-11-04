<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

use PHPUnit\Framework\TestCase;
use Plenty\Repositories\Models\PaginatedResult;
use Wayfair\PlentyMockets\AbstractMockFactory;

class MockPaginatedResultFactory extends AbstractMockFactory
{
    private $pageNumber = 1;

    private $totalCount;

    private $createdLastPage = false;

    public function __construct(TestCase $testCase, int $totalCount)
    {
        parent::__construct($testCase);
        $this->totalCount = $totalCount;
    }

    public function createNext(array $searchResults, bool $isLastPage): PaginatedResult
    {
        if ($this->createdLastPage)
        {
            throw new \Exception("Already created last page");
        }

        /** @var PaginatedResult&\PHPUnit\Framework\MockObject\MockObject */
        $paginatedResult  = $this->createMock(PaginatedResult::class);

        $curPageNumber = $this->pageNumber++;

        $paginatedResult->method('getResult')->willReturn($searchResults);

        $paginatedResult->method('getPage')->willReturn($curPageNumber);

        $paginatedResult->method('getCurrentPage')->willReturn($curPageNumber);

        $paginatedResult->method('getTotalCount')->willReturn($this->totalCount);

        $paginatedResult->method('isLastPage')->willReturn($isLastPage);

        $this->createdLastPage = $this->createdLastPage || $isLastPage;

        return $paginatedResult;
    }
}
