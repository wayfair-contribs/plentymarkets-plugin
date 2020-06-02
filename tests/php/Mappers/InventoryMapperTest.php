<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

use Plenty\Modules\Item\VariationStock\Models\VariationStock;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Helpers\ConfigHelper;

final class InventoryMapperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test Merging Quantities in nullable mode
     * @dataProvider dataProviderForInventoryQuantityMerge
     */
    public function testMergeQuantities($left, $right, $expected, $msg)
    {
        $result = InventoryMapper::mergeInventoryQuantities($left, $right);
        $this->assertEquals($expected, $result, $msg);
    }

    public function dataProviderForInventoryQuantityMerge()
    {
        return [
            [null, null, null, "two nulls should make null"],
            [null, -1, -1, "null and negative 1 should make negative 1"],
            [-1, null, -1, "null and negative 1 should make negative 1"],
            [null, 1, 1, "null and 1 should make 1"],
            [1, null, 1, "1 and null should make 1"],
            [null, 0, 0, "null and zero should make zero"],
            [0, null, 0, "zero and null should make zero"],
            [0, 0, 0, "zero and zero should make zero"],
            [-1, 0, -1, "negative 1 and zero should make negative 1"],
            [0, -1, -1, "zero and negative 1 should make negative 1"],
            [-1, 1, 1, "negative 1 and 1 should make positive 1"],
            [1, -1, 1, "1 and negative 1 should make postiive 1"],
            [0, 1, 1, "zero and 1 should make 1"],
            [1, 0, 1, "1 and zero should make 1"],
            [1, 1, 2, "1 and 1 should make 2"],
            [5, 9, 14, "5 and 9 should make 14"],
            [9, 5, 14, "9 and 5 should make 14"],
            [-1, -1, -1, "two negative 1s should make -1"],
            [-2, -2, -1, "two negative 2s should make -1"],
            [-2, 5, 5, "negative 2 and five should make five"],
            [5, -2, 5, "five and negative 2 should make five"]
        ];
    }

    /**
     * Make sure a null stock row always returns null quantity on hand
     */
    public function testGetQuantityOnHandNullStock()
    {
        $configHelper = $this->createMock(ConfigHelper::class);
        $this->assertEquals(null, InventoryMapper::getQuantityOnHand(null, null), "both inputs null");
        $this->assertEquals(null, InventoryMapper::getQuantityOnHand(null, $configHelper), "uninitialized confighelper");

        $configHelper->method('getStockBufferValue')->will($this->returnValue(5));
        $this->assertEquals(null, InventoryMapper::getQuantityOnHand(null, $configHelper), "configHelper with positive buffer");

        $configHelper->method('getStockBufferValue')->will($this->returnValue(-5));
        $this->assertEquals(null, InventoryMapper::getQuantityOnHand(null, $configHelper), "configHelper with negative buffer");
    }

    /**
     * Make sure null config helper counts as zero stock buffer
     */
    public function testGetQuantityOnHandNullConfigHelper()
    {
        $variationStock = $this->createMock(VariationStock::class);
        $this->assertEquals(null, InventoryMapper::getQuantityOnHand(null, null), "both inputs null should make null");
        $this->assertEquals(null, InventoryMapper::getQuantityOnHand($variationStock, null), "uninitialized VariationStock should make null");

        $variationStock->netStock = 5;
        $this->assertEquals(5, InventoryMapper::getQuantityOnHand($variationStock, null), "VariationStock with positive stock should make postiive quantity");

        $variationStock->netStock = -1;
        $this->assertEquals(-1, InventoryMapper::getQuantityOnHand($variationStock, null), "VariationStock with negative one stock should make negative one quantity");

        $variationStock->netStock = -5;
        $this->assertEquals(-1, InventoryMapper::getQuantityOnHand($variationStock, null), "VariationStock with negative five stock should make negative one quantity");
    }

    /**
     * Test various stocks and buffers making quantity on hand
     * @dataProvider dataProviderForGetQuantityOnHand
     */
    public function testGetQuantityOnHand($netStock, $buffer, $expected, $msg = null)
    {
        $configHelper = null;
        $variationStock = null;
        
        // phpUnit 6.x (required for PHP 7.0.x) does not have createStub method.

        if (isset($buffer))
        {
            $configHelper = $this->createMock(AbstractConfigHelper::class);
            $configHelper->method('getStockBufferValue')->will($this->returnValue($buffer));
        }

        if (isset($netStock))
        {
            $variationStock = $this->createMock(VariationStock::class);
            $variationStock->netStock = $netStock;
        }

        $result = InventoryMapper::getQuantityOnHand($variationStock, $configHelper);

        $this->assertEquals($expected, $result, $msg);
    }

    public function dataProviderForGetQuantityOnHand()
    {
        return [
            [null, null, null, "null inputs should make null quanityOnHand"],
            [null, 0, null, "null stock with zero buffer should make null quanityOnHand"],
            [-5, -2, -1, "stock less than negative one with invalid buffer should make negative 1 quanityOnHand"],
            [-5, 0, -1, "stock less than negative one with zero buffer should make negative 1 quanityOnHand"],
            [-5, 5, -1, "stock less than negative one with postiive buffer should make negative 1 quanityOnHand"],
            [-1, -2, -1, "negative one stock with invalid buffer should make negative 1 quanityOnHand"],
            [-1, -1, -1, "negative one stock with negative one buffer should make negative 1 quantityOnHand"],
            [-1, 0, -1, "negative one stock with zero buffer should make negative 1 quantityOnHand"],
            [-1, 1, -1, "negative one stock with one buffer should make negative 1 quantityOnHand"],
            [-1, 5, -1, "negative one stock with positive buffer should make negative 1 quantityOnHand"],
            [0, null, 0, "zero stock with null buffer should make zero quanityOnHand"],
            [0, -2, 0,  "zero stock with negative buffer should make zero quanityOnHand"],
            [0, 0, 0,  "zero stock with zero buffer should make zero quanityOnHand"],
            [0, 2, 0,  "zero stock with positive buffer should make zero quanityOnHand"],
            [1, null, 1, "one stock with null buffer should make 1 quantityOnHand"],
            [1, 0, 1, "one stock with zero buffer should make 1 quantityOnHand"],
            [1, 1, 0, "one stock with 1 buffer should make 0 quantityOnHand"],
            [2, 5, 0, "two stock with five buffer should make zero quantityOnHand"],
            [5, -2, 5, "five stock with negative buffer should make five quantityOnHand"],
            [5, 5, 0, "five stock with five buffer should make zero quantityOnHand"],
            [5, 2, 3, "five stock with two buffer should make three quantityOnHand"]
        ];
    }
}
