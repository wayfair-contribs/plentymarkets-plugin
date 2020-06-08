<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

use Plenty\Plugin\ConfigRepository;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\ConfigHelper;

final class ConfigHelperTest extends \PHPUnit\Framework\TestCase {
    
    /**
     * Various tests for validateItemMappingMethod
     * 
     * @dataProvider dataProviderForNormalizeValidateItemMappingMethod
     */ 
    public function testNormalizeItemMappingMethod($input, $expected, $msg = null)
    {
        /** @var ConfigRepository */
        $configRepo = $this->createMock(ConfigRepository::class);
        $result = (new ConfigHelper($configRepo))->normalizeItemMappingMethod($input);

        $this->assertEquals($expected, $result, $msg);
    }

    public function dataProviderForNormalizeValidateItemMappingMethod()
    {
        $cases = [];

        $cases[] = [null, AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, "null input should default to variation number"];

        $cases[] = ['', AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, "blank input should default to variation number"];

        $cases[] = ['FakeMode', AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, "bad input should default to variation number"];

        foreach([AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, AbstractConfigHelper::ITEM_MAPPING_SKU, AbstractConfigHelper::ITEM_MAPPING_EAN] as $mode)
        {
            $cases[] = [$mode, $mode, "valid input should be returned as output"];
        }

        return $cases;
    }
}