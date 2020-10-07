<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;

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
}
