<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

use Plenty\Modules\Item\VariationStock\Models\VariationStock;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Mappers\InventoryMapper;

final class InventoryMapperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Test various cases of Merging Quantities
     * @dataProvider dataProviderForInventoryQuantityMerge
     */
    public function testMergeQuantities($left, $right, $expected, $msg)
    {
        $result = InventoryMapper::mergeInventoryQuantities($left, $right);
        $this->assertEquals($expected, $result, $msg);
    }

    /**
     * Test various stocks and buffers making quantity on hand
     * @dataProvider dataProviderForGetQuantityOnHand
     */
    public function testGetQuantityOnHand($netStock, $expected, $msg = null)
    {
        $variationStock = null;

        if (isset($netStock)) {
            // phpUnit 6.x (required for PHP 7.0.x) does not have createStub method.
            $variationStock = $this->createMock(VariationStock::class);
            $variationStock->netStock = $netStock;
        }

        $result = InventoryMapper::getQuantityOnHand($variationStock);

        $this->assertEquals($expected, $result, $msg);
    }

    /**
     * Stock buffer should be ignored when input DTO is null
     *
     * @return void
     */
    public function testApplyStockBufferNullDTO()
    {
        $this->assertNull(InventoryMapper::applyStockBuffer(null, -5));
        $this->assertNull(InventoryMapper::applyStockBuffer(null, -1));
        $this->assertNull(InventoryMapper::applyStockBuffer(null, 0));
        $this->assertNull(InventoryMapper::applyStockBuffer(null, 1));
        $this->assertNull(InventoryMapper::applyStockBuffer(null, 5));
    }

    /**
     * Test various stocks and buffers making quantity on hand
     * @dataProvider dataProviderForApplyStockBuffer
     */
    public function testApplyStockBuffer($onHand, $buffer, $expected, $msg = null)
    {
        $dtoData = [
            'supplierId' => 12345,
            'supplierPartNumber' => 'p324',
            'quantityOnHand' => $onHand,
            'quantityOnOrder' => 6,
        ];

        // RequestDTO::createFromArray uses PluginApp which doesn't work in test context.
        /** @var RequestDTO */
        $dto = new RequestDTO();
        $dto->setQuantityOnHand($onHand);

        $dto = InventoryMapper::applyStockBuffer($dto, $buffer);
        $result = $dto->getQuantityOnHand();

        $this->assertEquals($expected, $result, $msg);
    }

    /**
     * Test various cases for getting part numbers from variations
     *
     * @param [mixed] $variation
     * @param [mixed] $mappingMode
     * @param [mixed] $expected
     * @param [string] $msg
     *
     * @dataProvider dataProviderForGetSupplierPartNumberFromVariation
     */
    public function testGetSupplierPartNumberFromVariation($variation, $mappingMode, $expected, $msg = null)
    {
        $result = InventoryMapper::getSupplierPartNumberFromVariation($variation, $mappingMode);

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
            [1, -1, 1, "1 and negative 1 should make positive 1"],
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


    public function dataProviderForGetQuantityOnHand()
    {
        return [
            [null, null, "null stock should make null quantityOnHand"],
            [-5, -1, "stock less than negative one should make negative 1 quantityOnHand"],
            [-1, -1, "negative one stock should make negative 1 quantityOnHand"],
            [0, 0, "zero stock should make zero quantityOnHand"],
            [1, 1, "one stock should make 1 quantityOnHand"],
            [5, 5, "five stock should make five quantityOnHand"],
        ];
    }

    public function dataProviderForApplyStockBuffer()
    {
        return [
            [null, null, null, "null inputs should make null quantityOnHand"],
            [null, 0, null, "null input with zero buffer should make null quantityOnHand"],
            [-5, -2, -5, "input less than negative one with invalid buffer should not change output"],
            [-5, 0, -5, "input less than negative one with zero buffer should not change output"],
            [-5, 5, -5, "input less than negative one with positive buffer should not change output"],
            [-1, -2, -1, "negative one input with invalid buffer should make negative 1 quantityOnHand"],
            [-1, 0, -1, "negative one input with zero buffer should make negative 1 quantityOnHand"],
            [-1, 1, -1, "negative one input with one buffer should make negative 1 quantityOnHand"],
            [-1, 5, -1, "negative one input with positive buffer should make negative 1 quantityOnHand"],
            [0, null, 0, "zero input with null buffer should make zero quantityOnHand"],
            [0, -2, 0,  "zero input with negative buffer should make zero quantityOnHand"],
            [0, 0, 0,  "zero input with zero buffer should make zero quantityOnHand"],
            [0, 2, 0,  "zero input with positive buffer should make zero quantityOnHand"],
            [1, null, 1, "one input with null buffer should make 1 quantityOnHand"],
            [1, 0, 1, "one input with zero buffer should make 1 quantityOnHand"],
            [1, 1, 0, "one input with 1 buffer should make 0 quantityOnHand"],
            [2, 5, 0, "two input with five buffer should make zero quantityOnHand"],
            [5, -2, 5, "five input with negative buffer should make five quantityOnHand"],
            [5, 5, 0, "five input with five buffer should make zero quantityOnHand"],
            [5, 2, 3, "five input with two buffer should make three quantityOnHand"]
        ];
    }

    public function dataProviderForGetSupplierPartNumberFromVariation()
    {
        $cases = [];

        // cases with null variation
        $cases[] = [null, null, null, "Null inputs should result in not finding any part number"];
        $cases[] = [null, AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, null, "Null Variation with Number mode should result in not finding any part number"];
        $cases[] = [null, AbstractConfigHelper::ITEM_MAPPING_EAN, null, "Null Variation with EAN mode should result in not finding any part number"];
        $cases[] = [null, AbstractConfigHelper::ITEM_MAPPING_SKU, null, "Null Variation with SKU mode should result in not finding any part number"];

        $keyId = 'id';
        $keyNumber = 'number';
        $keyVariationBarcodes = 'variationBarcodes';
        $keyVariationSkus = 'variationSkus';
        $keyCode = 'code';
        $keySku = 'sku';

        $mockId = 2;
        $mockNumber = 8000;
        $mockBarcodes = [[$keyCode => '1234567891011']];
        $mockSkus = [[$keySku => 'ABCDEFG123']];

        $variationWithNumberOnly = [];
        $variationWithNumberOnly[$keyId] = $mockId;
        $variationWithNumberOnly[$keyNumber] = $mockNumber;

        /** @var Variation */
        $variationWithNumberAndBarcode = [];
        $variationWithNumberAndBarcode[$keyId] = $mockId;
        $variationWithNumberAndBarcode[$keyNumber] = $mockNumber;
        $variationWithNumberAndBarcode[$keyVariationBarcodes] = $mockBarcodes;

        /** @var Variation */
        $variationWithNumberAndSku = [];
        $variationWithNumberAndSku[$keyId] = $mockId;
        $variationWithNumberAndSku[$keyNumber] = $mockNumber;
        $variationWithNumberAndSku[$keyVariationSkus] = $mockSkus;

        /** @var Variation */
        $variationWithEverything = [];
        $variationWithEverything[$keyId] = $mockId;
        $variationWithEverything[$keyNumber] = $mockNumber;
        $variationWithEverything[$keyVariationBarcodes] = $mockBarcodes;
        $variationWithEverything[$keyVariationSkus] = $mockSkus;

        $variationsUnderTest = [$variationWithNumberOnly, $variationWithNumberAndBarcode, $variationWithEverything];
        foreach ($variationsUnderTest as $variation) {
            $cases[] = [$variation, "FakeItemMappingMethod", $variation[$keyNumber], "bogus mapping method defaults to Variation number"];

            $cases[] = [
                $variation, AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, $variation[$keyNumber],
                "choosing variation number should return variation number regardless of other settings"
            ];

            $expectedPartNo = null;
            if (isset($variation[$keyVariationBarcodes]) && !empty($variation[$keyVariationBarcodes])) {
                $expectedPartNo = $variation[$keyVariationBarcodes][0][$keyCode];
            }

            $cases[] = [
                $variation, AbstractConfigHelper::ITEM_MAPPING_EAN, $expectedPartNo,
                "choosing EAN should use EAN"
            ];

            $expectedPartNo = null;
            if (isset($variation[$keyVariationSkus]) && !empty($variation[$keyVariationSkus])) {
                $expectedPartNo = $variation[$keyVariationSkus][0][$keySku];
            }

            $cases[] = [
                $variation, AbstractConfigHelper::ITEM_MAPPING_SKU, $expectedPartNo,
                "choosing SKU should use SKU"
            ];
        }

        return $cases;
    }
}
