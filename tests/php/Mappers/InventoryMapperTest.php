<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

$plentymocketsFactoriesDirPath = dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Factories' . DIRECTORY_SEPARATOR;

require_once($plentymocketsFactoriesDirPath
    . 'VariationDataFactory.php');

require_once($plentymocketsFactoriesDirPath
    . 'VariationBarcodeDataFactory.php');

require_once($plentymocketsFactoriesDirPath
    . 'VariationSkuDataFactory.php');

use Exception;
use InvalidArgumentException;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\PlentyMockets\Factories\VariationBarcodeDataFactory;
use Wayfair\PlentyMockets\Factories\VariationSkuDataFactory;
use Wayfair\PlentyMockets\Factories\VariationDataFactory;

/**
 * Tests for InventoryMapper
 *
 * TODO: add tests for "createInventoryDTOsFromVariation" method
 */
final class InventoryMapperTest extends \PHPUnit\Framework\TestCase
{
    const KNOWN_MARKET_ID = 12345;
    const UNUSED_MARKET_ID = 9999999;
    const OTHER_MARKET_ID = 22222;

    /**
     * Test various cases of Merging Quantities
     * @dataProvider dataProviderForInventoryQuantityMerge
     */
    public function testMergeQuantities($msg, $expected, $left, $right)
    {
        $result = InventoryMapper::mergeInventoryQuantities($left, $right);
        $this->assertEquals($expected, $result, $msg);
    }

    /**
     * Test various stocks and buffers making quantity on hand
     * @dataProvider dataProviderForNormalizeQuantityOnHand
     */
    public function testNormalizeQuantityOnHand($msg, $expected, $netStock)
    {

        $result = InventoryMapper::normalizeQuantityOnHand($netStock);

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
    public function testApplyStockBuffer($msg = null, $expected, $onHand, $buffer)
    {
        $dtoData = [
            'supplierId' => 12345,
            'supplierPartNumber' => 'p324',
            'quantityOnHand' => $onHand,
            'quantityOnOrder' => 6,
        ];

        $dto = new RequestDTO();
        $dto->setQuantityOnHand($onHand);

        $dto = InventoryMapper::applyStockBuffer($dto, $buffer);
        $result = $dto->getQuantityOnHand();

        $this->assertEquals($expected, $result, $msg);
    }

    /**
     * Test various cases for getting part numbers from variations
     *
     * @param mixed $msg
     * @param mixed $variation
     * @param mixed $mappingMode
     * @param mixed $referrerId
     * @param mixed $expected
     *
     * @dataProvider dataProviderForGetSupplierPartNumberFromVariation
     */
    public function testGetSupplierPartNumberFromVariation($msg, $expected, $variation, $mappingMode, $referrerId = null)
    {
        if (isset($mappingMode)) {

            if (!isset($expected)) {
                $this->expectException(Exception::class);
            }
        } else {
            $this->expectException(InvalidArgumentException::class);
        }


        $result = InventoryMapper::getSupplierPartNumberFromVariation($variation, $mappingMode, $referrerId);

        if (isset($mappingMode) && isset($expected)) {
            $this->assertEquals($expected, $result, $msg);
        }
    }

    public function dataProviderForInventoryQuantityMerge()
    {
        return [
            ["two nulls should make null", null, null, null],
            ["null and negative 1 should make negative 1", -1, null, -1],
            ["null and negative 1 should make negative 1", -1, -1,  null],
            ["null and 1 should make 1", 1, null, 1],
            ["1 and null should make 1", 1, 1,  null],
            ["null and zero should make zero", 0, null, 0],
            ["zero and null should make zero", 0, 0, null],
            ["zero and zero should make zero", 0, 0, 0],
            ["negative 1 and zero should make negative 1", -1, -1, 0],
            ["zero and negative 1 should make negative 1", -1, 0, -1],
            ["negative 1 and 1 should make positive 1", 1, -1, 1],
            ["1 and negative 1 should make positive 1", 1, 1, -1],
            ["zero and 1 should make 1", 1, 0, 1],
            ["1 and zero should make 1", 1, 1, 0],
            ["1 and 1 should make 2", 2, 1, 1],
            ["5 and 9 should make 14", 14, 5, 9],
            ["9 and 5 should make 14", 14, 9, 5],
            ["two negative 1s should make -1", -1, -1, -1],
            ["two negative 2s should make -1", -1, -2, -2],
            ["negative 2 and five should make five", 5, -2, 5],
            ["five and negative 2 should make five", 5, 5, -2]
        ];
    }


    public function dataProviderForNormalizeQuantityOnHand()
    {
        return [
            ["null stock should make null quantityOnHand", null, null],
            ["stock less than negative one should make negative 1 quantityOnHand", -1, -5],
            ["negative one stock should make negative 1 quantityOnHand", -1, -1],
            ["zero stock should make zero quantityOnHand", 0, 0],
            ["one stock should make 1 quantityOnHand", 1, 1],
            ["five stock should make five quantityOnHand", 5, 5],
        ];
    }

    public function dataProviderForApplyStockBuffer()
    {
        return [
            ["null inputs should make null quantityOnHand", null, null, null],
            ["null input with zero buffer should make null quantityOnHand", null, null, 0],
            ["input less than negative one with invalid buffer should not change output", -5, -5, -2],
            ["input less than negative one with zero buffer should not change output", -5, -5, 0],
            ["input less than negative one with positive buffer should not change output", -5, -5, 5],
            ["negative one input with invalid buffer should make negative 1 quantityOnHand", -1, -1, -2],
            ["negative one input with zero buffer should make negative 1 quantityOnHand", -1, -1, 0],
            ["negative one input with one buffer should make negative 1 quantityOnHand", -1, -1, 1],
            ["negative one input with positive buffer should make negative 1 quantityOnHand", -1, -1, 5],
            ["zero input with null buffer should make zero quantityOnHand", 0, 0, null],
            ["zero input with negative buffer should make zero quantityOnHand", 0, 0, -2],
            ["zero input with zero buffer should make zero quantityOnHand", 0, 0, 0],
            ["zero input with positive buffer should make zero quantityOnHand", 0, 0, 2],
            ["one input with null buffer should make 1 quantityOnHand", 1, 1, null],
            ["one input with zero buffer should make 1 quantityOnHand", 1, 1, 0],
            ["one input with 1 buffer should make 0 quantityOnHand", 0, 1, 1],
            ["two input with five buffer should make zero quantityOnHand", 0, 2, 5],
            ["five input with negative buffer should make five quantityOnHand", 5, 5, -2],
            ["five input with five buffer should make zero quantityOnHand", 0, 5, 5],
            ["five input with two buffer should make three quantityOnHand", 3, 5, 2]
        ];
    }

    public function dataProviderForGetSupplierPartNumberFromVariation()
    {
        $cases = [];

        // cases with null variation
        $cases[] = ["Null inputs should result in not finding any part number", null, null, null];
        $cases[] = ["Null Variation with Number mode should throw InvalidArgumentException", null, null, AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, null];
        $cases[] = ["Null Variation with EAN mode should throw InvalidArgumentException", null, null, AbstractConfigHelper::ITEM_MAPPING_EAN, null];
        $cases[] = ["Null Variation with SKU mode should throw InvalidArgumentException", null, null, AbstractConfigHelper::ITEM_MAPPING_SKU, null];

        $variationDataFactory = new VariationDataFactory();

        $marketCombinations = [null, [], [self::OTHER_MARKET_ID], [self::KNOWN_MARKET_ID], [self::KNOWN_MARKET_ID, self::OTHER_MARKET_ID], [self::OTHER_MARKET_ID, self::KNOWN_MARKET_ID]];
        foreach ($marketCombinations as $markets) {
            for ($numBarcodes = 0; $numBarcodes < 3; $numBarcodes++) {
                $variation = $variationDataFactory->create($numBarcodes, $markets);

                $cases[] = [
                    "bogus mapping method defaults to Variation number",
                    $variation[VariationDataFactory::COL_NUMBER],
                    $variation, "FakeItemMappingMethod", null
                ];

                $cases[] = [
                    "choosing variation number should return variation number regardless of other settings",
                    $variation[VariationDataFactory::COL_NUMBER],
                    $variation, AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, null
                ];

                if (isset($variation[VariationDataFactory::COL_BARCODES]) && !empty($variation[VariationDataFactory::COL_BARCODES])) {
                    $cases[] = [
                        "choosing EAN should use EAN",
                        $variation[VariationDataFactory::COL_BARCODES][0][VariationBarcodeDataFactory::COL_CODE],
                        $variation, AbstractConfigHelper::ITEM_MAPPING_EAN, null
                    ];
                } else {
                    $cases[] = [
                        "choosing EAN when there are no barcodes should fail",
                        null,
                        $variation, AbstractConfigHelper::ITEM_MAPPING_EAN, null
                    ];
                }

                if (isset($variation[VariationDataFactory::COL_SKUS]) && !empty($variation[VariationDataFactory::COL_SKUS])) {
                    $firstSku = $variation[VariationDataFactory::COL_SKUS][0][VariationSkuDataFactory::COL_SKU];

                    $skuForKnownMarket = null;
                    foreach ($variation[VariationDataFactory::COL_SKUS] as $variationSku) {
                        if ($variationSku[VariationSkuDataFactory::COL_MARKET_ID] == self::KNOWN_MARKET_ID) {
                            $skuForKnownMarket = $variationSku[VariationSkuDataFactory::COL_SKU];
                            break;
                        }
                    }

                    $cases[] = [
                        "choosing SKU without referrer should use first SKU",
                        $firstSku,
                        $variation, AbstractConfigHelper::ITEM_MAPPING_SKU, null
                    ];

                    if (isset($skuForKnownMarket)) {

                        $cases[] = [
                            "choosing SKU and supplying a valid referrer should use correct market's SKU",
                            $skuForKnownMarket,
                            $variation, AbstractConfigHelper::ITEM_MAPPING_SKU, self::KNOWN_MARKET_ID
                        ];
                    }

                    $cases[] = [
                        "choosing SKU and supplying an invalid referrer should use first SKU",
                        $firstSku,
                        $variation, AbstractConfigHelper::ITEM_MAPPING_SKU, self::UNUSED_MARKET_ID
                    ];
                } else {
                    $cases[] = [
                        "choosing SKU and no referrer without any SKUs should fail",
                        null,
                        $variation, AbstractConfigHelper::ITEM_MAPPING_SKU, null
                    ];

                    $cases[] = [
                        "choosing SKU and supplying a valid referrer without any SKUs should fail",
                        null,
                        $variation, AbstractConfigHelper::ITEM_MAPPING_SKU, self::KNOWN_MARKET_ID
                    ];

                    $cases[] = [
                        "choosing SKU and supplying an invalid referrer without any SKUs should fail",
                        null,
                        $variation, AbstractConfigHelper::ITEM_MAPPING_SKU, self::UNUSED_MARKET_ID
                    ];
                }
            }
        }
        return $cases;
    }
}
