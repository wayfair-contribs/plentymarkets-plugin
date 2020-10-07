<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

require_once(__DIR__ . '/AbstractMockFactory.php');

use Plenty\Repositories\Models\PaginatedResult;
use Wayfair\PlentyMockets\AbstractMockFactory;

class MockPaginatedResultFactory extends AbstractMockFactory
{
    public function create(array $searchResults, bool $isLastPage): PaginatedResult
    {
        /** @var PaginatedResult&\PHPUnit\Framework\MockObject\MockObject */
        $paginatedResult  = $this->createMock(PaginatedResult::class);

        $paginatedResult->method('getResult')->willReturn($searchResults);
        $paginatedResult->method('isLastPage')->willReturn($isLastPage);

        return $paginatedResult;
    }
}
