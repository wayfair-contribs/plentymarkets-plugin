<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Helpers;

use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Plugin\CachingRepository;
use Plenty\Plugin\ConfigRepository;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Repositories\KeyValueRepository;

class ConfigHelper extends AbstractConfigHelper
{

  const CACHING_MINUTES = 360;

  const LOG_KEY_UNDEFINED_MAPPING_METHOD = 'undefinedMappingMethod';

  const KNOWN_ITEM_MAPPING_METHODS = [
    AbstractConfigHelper::ITEM_MAPPING_SKU,
    AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
    AbstractConfigHelper::ITEM_MAPPING_EAN
  ];

  /**
   * @var ConfigRepository
   */
  protected $config;

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var CachingRepository
   */
  private $cachingRepository;

  /**
   * @var PluginRepositoryContract
   */
  private $pluginRepository;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * ConfigHelper constructor.
   *
   * @param ConfigRepository $config
   */
  public function __construct(
    ConfigRepository $config,
    KeyValueRepository $keyValueRepository,
    CachingRepository $cachingRepository,
    PluginRepositoryContract $pluginRepository,
    LoggerContract $logger
  ) {
    $this->config = $config;
    $this->keyValueRepository = $keyValueRepository;
    $this->cachingRepository = $cachingRepository;
    $this->pluginRepository = $pluginRepository;
    $this->logger = $logger;
  }

  /**
   * @return mixed
   */
  public function getClientId()
  {
    return $this->config->get(self::PLUGIN_NAME . '.global.container.clientId');
  }

  /**
   * @return mixed
   */
  public function getClientSecret()
  {
    return $this->config->get(self::PLUGIN_NAME . '.global.container.clientSecret');
  }

  /**
   * @return float
   */
  public function getOrderReferrerValue(): float
  {
    if ($this->cachingRepository->has(self::SETTINGS_ORDER_REFERRER_KEY)) {
      return $this->cachingRepository->get(self::SETTINGS_ORDER_REFERRER_KEY);
    }

    $value = $this->keyValueRepository->get(self::SETTINGS_ORDER_REFERRER_KEY);
    $this->cachingRepository->put(self::SETTINGS_ORDER_REFERRER_KEY, $value, self::CACHING_MINUTES);
    return $value;
  }

  /**
   * @return int|mixed
   */
  public function getStockBufferValue()
  {
    return (int) $this->keyValueRepository->get(self::SETTINGS_STOCK_BUFFER_KEY);
  }

  /**
   * @return string
   */
  public function getDryRun(): string
  {
    return (string) $this->config->get(self::PLUGIN_NAME . '.global.container.dryRunMode');
  }

  public function isAllItemsActive(): bool
  {
    return (bool) ($this->keyValueRepository->get(self::SETTINGS_SEND_ALL_ITEMS_KEY) ?? false);
  }

  /**
   * @return string
   */
  public function getImportOrderSince()
  {
    return $this->keyValueRepository->get(self::IMPORT_ORDER_SINCE);
  }

  /**
   * @return string
   */
  public function getPluginVersion(): string
  {
    $plugin = $this->pluginRepository->getPluginByName(AbstractConfigHelper::PLUGIN_NAME);
    $plugin = $this->pluginRepository->decoratePlugin($plugin);
    return $plugin->versionProductive;
  }

  /**
   * @return string
   */
  public function getIntegrationAgentHeader()
  {
    return self::INTEGRATION_AGENT_NAME . ' - v:' . $this->getPluginVersion();
  }

  /**
   * Get the item mapping mode for Inventory
   *
   * @return string
   */
  public function getItemMappingMethod()
  {
    $itemMappingMethod = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);
    return $this->normalizeItemMappingMethod($itemMappingMethod);
  }

  /**
   * Check an Item Mapping Method choice against known values
   *
   * @param string $itemMappingMethod
   * @return bool
   */
  public function validateItemMappingMethod($itemMappingMethod)
  {
    return isset($itemMappingMethod) && !empty($itemMappingMethod) && in_array($itemMappingMethod, self::KNOWN_ITEM_MAPPING_METHODS);
  }

  /**
   * Validate an itemMappingMethod choice,
   * Defaulting to Variation Number
   *
   * @param string $itemMappingMethod
   * @return string
   */
  function normalizeItemMappingMethod($itemMappingMethod)
  {
    if (!$this->validateItemMappingMethod($itemMappingMethod)) {
      $this->logger->warning(
        TranslationHelper::getLoggerKey(self::LOG_KEY_UNDEFINED_MAPPING_METHOD),
        [
          'additionalInfo' => [
            'itemMappingMethodFound' => $itemMappingMethod,
            'defaultingTo' => AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
          ],
          'method' => __METHOD__
        ]
      );

      return AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER;
    }

    return $itemMappingMethod;
  }
}
