<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'MockPaginatedResultFactory.php');

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Wayfair\PlentyMockets\Factories\MockPaginatedResultFactory;

/**
 * Base class for Utilities that create generating Mock objects for TestCases
 */
abstract class AbstractMockFactory
{
    private $testCase;

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    protected function getTestCase(): TestCase
    {
        return $this->testCase;
    }

    /**
     * helper function, because createMock in TestCase is protected
     */
    protected function createMock(string $originalClassName)
    {
        return (new MockBuilder($this->testCase, $originalClassName))
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->getMock();
    }

    protected function configureRepository(string $searchMethodName, MockObject $mockObject, $dataPages, array $expectedFilters = null, int $searchesExpected = null)
    {
        $pages = [];
        $dataForLastPage = [];

        $totalCount = $this->countObjectsInPages($dataPages);

        $pageFactory = new MockPaginatedResultFactory($this->getTestCase(), $totalCount);

        $amtPages = count($dataPages);

        $amountResults = 0;

        for ($page = 0; $page < $amtPages - 1; $page++) {
            $pages[] = $pageFactory->createNext($dataPages[$page], false);
        }

        if ($amtPages > 0) {
            $dataForLastPage = $dataPages[$amtPages - 1];
        }

        // there's always at least one page returned by Plenty, even if there are no results.
        // the last page MUST report that it is the last page, or there will be infinite looping!
        $pages[] = $pageFactory->createNext($dataForLastPage, true);

        if (!isset($searchesExpected)) {
            $searchesExpected = $amtPages;
        }

        // TODO: it would be more accurate to call out to a method that returns the array for page num in search params
        $mockObject->expects($this->getTestCase()->exactly($searchesExpected))->method($searchMethodName)->willReturnOnConsecutiveCalls(...$pages);

        if (isset($expectedFilters)) {
            $mockObject->expects($this->getTestCase()->once())->method('setFilters')->with($this->getTestCase()->equalTo($expectedFilters));
        }
    }

    protected function countObjectsInPages($dataPages): int
    {
        if (!isset($dataPages) || empty($dataPages)) {
            return 0;
        }

        $totalCount = 0;

        foreach ($dataPages as $idx => $page) {
            $totalCount += sizeof($page);
        }

        return $totalCount;
    }
}
