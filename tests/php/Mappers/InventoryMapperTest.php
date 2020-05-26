<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

final class InventoryMapperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider dataProviderForInventoryQuantityMerge
     */
    public function testMergeQuantities($left, $right, $expected, $msg)
    {
        $result = InventoryMapper::mergeInventoryQuantities($left, $right);
        $this->assertEquals($expected, $result, $msg);
    }

    public function dataProviderForInventoryQuantityMerge()
    {
        return array(
            array(null, null, null, "two nulls should make null"),
            array(null, -1, -1, "null and negative 1 should make negative 1"),
            array(-1, null, -1, "null and negative 1 should make negative 1"),
            array(null, 1, 1, "null and 1 should make 1"),
            array (1, null, 1, "1 and null should make 1"),
            array(null, 0, 0, "null and zero should make zero"),
            array(0, null, 0, "zero and null should make zero"),
            array(0, 0, 0, "zero and zero should make zero"),
            array(-1, 0, -1, "negative 1 and zero should make negative 1"),
            array(0, -1, -1, "zero and negative 1 should make negative 1"),
            array(-1, 1, 1, "negative 1 and 1 should make positive 1"),
            array(1, -1, 1, "1 and negative 1 should make postiive 1"),
            array(0, 1, 1, "zero and 1 should make 1"),
            array(1, 0, 1, "1 and zero should make 1"),
            array(1, 1, 2, "1 and 1 should make 2"),
            array(5, 9, 14, "5 and 9 should make 14"),
            array(9, 5, 14, "9 and 5 should make 14"),
            array(-1, -1, -1, "two negative 1s should make -1"),
            array(-2, -2, 0, "two negative 2s should make 0"),
            array(-2, 5, 5, "negative 2 and five should make five"),
            array(5, -2, 5, "five and negative 2 should make five")
        );
    }
}
