<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Plugin\CachingRepository;
use Plenty\Plugin\ConfigRepository;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Repositories\KeyValueRepository;

final class ConfigHelperTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Various tests for validateItemMappingMethod
     *
     * @dataProvider dataProviderForNormalizeValidateItemMappingMethod
     */
    public function testNormalizeItemMappingMethod($input, $expected, $msg = null)
    {
        /** @var ConfigRepository&\PHPUnit\Framework\MockObject\MockObject */
        $configRepo = $this->createMock(ConfigRepository::class);
        /** @var KeyValueRepository&\PHPUnit\Framework\MockObject\MockObject */
        $keyValueRepo = $this->createMock(KeyValueRepository::class);
        /** @var CachingRepository&\PHPUnit\Framework\MockObject\MockObject */
        $cachingRepo = $this->createMock(CachingRepository::class);
        /** @var PluginRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $pluginRepo = $this->createMock(PluginRepositoryContract::class);
        /** @var LoggerContract&\PHPUnit\Framework\MockObject\MockObject */
        $logger = $this->createMock(LoggerContract::class);

        $configHelper = $this->createConfigHelper();
        $result = $configHelper->normalizeItemMappingMethod($input);

        $this->assertEquals($expected, $result, $msg);
    }

    public function dataProviderForNormalizeValidateItemMappingMethod()
    {
        $cases = [];

        $cases[] = [null, AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, "null input should default to variation number"];

        $cases[] = ['', AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, "blank input should default to variation number"];

        $cases[] = ['FakeMode', AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, "bad input should default to variation number"];

        foreach ([AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER, AbstractConfigHelper::ITEM_MAPPING_SKU, AbstractConfigHelper::ITEM_MAPPING_EAN] as $mode) {
            $cases[] = [$mode, $mode, "valid input should be returned as output"];
        }

        return $cases;
    }

    /**
     * @return ConfigHelper
     */
    protected function createConfigHelper(): ConfigHelper
    {
        /** @var ConfigRepository&\PHPUnit\Framework\MockObject\MockObject */
        $configRepo = $this->createMock(ConfigRepository::class);
        /** @var KeyValueRepository&\PHPUnit\Framework\MockObject\MockObject */
        $keyValueRepo = $this->createMock(KeyValueRepository::class);
        /** @var CachingRepository&\PHPUnit\Framework\MockObject\MockObject */
        $cachingRepo = $this->createMock(CachingRepository::class);
        /** @var PluginRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $pluginRepo = $this->createMock(PluginRepositoryContract::class);
        /** @var LoggerContract&\PHPUnit\Framework\MockObject\MockObject */
        $logger = $this->createMock(LoggerContract::class);

        return new ConfigHelper($configRepo, $keyValueRepo, $cachingRepo, $pluginRepo, $logger);
    }
}
