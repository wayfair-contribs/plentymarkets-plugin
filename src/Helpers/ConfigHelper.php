<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
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
   * Flag to set after boot has been completed
   *
   * @var boolean
   */
  private static $bootFlag = false;

  /**
   * ConfigHelper constructor.
   *
   * @param ConfigRepository $config
   */
  public function __construct(ConfigRepository $config)
  {
    $this->config = $config;
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
   * @return int
   */
  public function getOrderReferrerValue(): int
  {
    /**
     * @var KeyValueRepository $keyValueRepository
     */
    $keyValueRepository = pluginApp(KeyValueRepository::class);
    /**
     * @var CachingRepository $cachingRepository
     */
    $cachingRepository = pluginApp(CachingRepository::class);
    if ($cachingRepository->has(self::SETTINGS_ORDER_REFERRER_KEY)) {
      return $cachingRepository->get(self::SETTINGS_ORDER_REFERRER_KEY);
    }
    $value = (int) $keyValueRepository->get(self::SETTINGS_ORDER_REFERRER_KEY);
    $cachingRepository->put(self::SETTINGS_ORDER_REFERRER_KEY, $value, self::CACHING_MINUTES);
    return $value;
  }

  /**
   * @return int|mixed
   */
  public function getStockBufferValue()
  {
    /**
     * @var KeyValueRepository $keyValueRepository
     */
    $keyValueRepository = pluginApp(KeyValueRepository::class);

    return (int) $keyValueRepository->get(self::SETTINGS_STOCK_BUFFER_KEY);
  }

  /**
   * @return string
   */
  public function getDryRun(): string
  {
    return $this->config->get(self::PLUGIN_NAME . '.global.container.dryRunMode');
  }

  public function isAllItemsActive(): bool
  {
    /**
     * @var KeyValueRepository $keyValueRepository
     */
    $keyValueRepository = pluginApp(KeyValueRepository::class);

    return (bool) $keyValueRepository->get(self::SETTINGS_SEND_ALL_ITEMS_KEY);
  }

  /**
   * @return string
   */
  public function getImportOrderSince()
  {
    /**
     * @var KeyValueRepository $keyValueRepository
     */
    $keyValueRepository = pluginApp(KeyValueRepository::class);

    return $keyValueRepository->get(self::IMPORT_ORDER_SINCE);
  }

  /**
   * @return string
   */
  public function getPluginVersion(): string
  {
    /** @var PluginRepositoryContract */
    $pluginRepo = pluginApp(PluginRepositoryContract::class);
    $plugin = $pluginRepo->getPluginByName(AbstractConfigHelper::PLUGIN_NAME);
    $plugin = $pluginRepo->decoratePlugin($plugin);
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
   * Set the flag for booting to true
   *
   * @return void
   */
  public static function setBootFlag()
  {
    self::$bootFlag = true;
  }

  /**
   * Check if boot completed
   *
   * @return bool
   */
  public function hasBooted(): bool
  {
    return self::$bootFlag;
  }

  /**
   * Get the item mapping mode for Inventory
   *
   * @return string
   */
  public function getItemMappingMethod()
  {
    /** @var KeyValueRepository $keyValueRepository */
    $keyValueRepository = pluginApp(KeyValueRepository::class);
    $itemMappingMethod = $keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);

    /** @var LoggerContract $logger */
    $logger = pluginApp(LoggerContract::class);

    return $this->normalizeItemMappingMethod($itemMappingMethod, $logger);
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
  function normalizeItemMappingMethod($itemMappingMethod, $logger = null)
  {
    if (!$this->validateItemMappingMethod($itemMappingMethod)) {

      if (isset($logger)) {
        $logger->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_UNDEFINED_MAPPING_METHOD),
          [
            'additionalInfo' => [
              'itemMappingMethodFound' => $itemMappingMethod,
              'defaultingTo' => AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
            ],
            'method' => __METHOD__
          ]
        );
      }

      return AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER;
    }

    return $itemMappingMethod;
  }
  
}
