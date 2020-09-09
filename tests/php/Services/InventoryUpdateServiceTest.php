<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Services;

use InvalidArgumentException;
use Wayfair\Services\InventoryUpdateService;

final class InventoryUpdateServiceTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Test application of time window
     * @dataProvider dataProviderForTestApplyTimeFilter
     */
    public function testApplyTimeFilter(int $start, int $end = null)
    {
        $myArray = [];

        // give a 5 second buffer between calculating the here
        // and calculating the current time in the function under test
        if ($start <= 0 || $start > time() + 5 || (isset($end) && $start >= $end)) {
            $this->expectException(InvalidArgumentException::class);
        }

        if (isset($end)) {
            $myArray = InventoryUpdateService::applyTimeFilter($myArray, $start, $end);
        } else {
            $myArray = InventoryUpdateService::applyTimeFilter($myArray, $start);
        }


        $this->assertTrue(array_key_exists('updatedBetween', $myArray));
        $this->assertNotNull($myArray['updatedBetween']);
        $this->assertNotNull($myArray['updatedBetween']['timestampFrom']);
        $this->assertNotNull($myArray['updatedBetween']['timestampTo']);

        $this->assertEquals($start, $myArray['updatedBetween']['timestampFrom']);

        if (isset($end)) {
            $this->assertEquals($end, $myArray['updatedBetween']['timestampTo']);
        }

        $this->assertTrue($myArray['updatedBetween']['timestampFrom'] < $myArray['updatedBetween']['timestampTo']);
    }

    public function dataProviderForTestApplyTimeFilter()
    {
        $future = time() + 10000;
        return
            [
                [-1, -1],
                [-1, null],
                [-1, 0],
                [-1, 1],
                [-1, 2],
                [-1, $future],
                [0, -1],
                [0, null],
                [0, 0],
                [0, 1],
                [0, 2],
                [0, $future],
                [1, -1],
                [1, null],
                [1, 0],
                [1, 1],
                [1, 2],
                [1, $future],
                [2, -1],
                [2, null],
                [2, 0],
                [2, 1],
                [2, 2],
                [2, $future],
                [$future, -1],
                [$future, null],
                [$future, 0],
                [$future, 1],
                [$future, 2],
                [$future, $future]
            ];
    }
}
