<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Helpers;

use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Plugin\CachingRepository;
use Plenty\Plugin\ConfigRepository;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Repositories\KeyValueRepository;

class ConfigHelper implements ConfigHelperContract
{

  const CACHING_MINUTES = 360;

  const LOG_KEY_TEST_MODE_OFF = 'testModeOff';
  const LOG_KEY_TEST_MODE_ON = 'testModeOn';

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
  public function getWayfairClientId()
  {
    return $this->config->get(self::PLUGIN_NAME . '.global.container.clientId');
  }

  /**
   * @return mixed
   */
  public function getWayfairClientSecret()
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
    $pluginRepo = pluginApp(PluginRepositoryContract::class);
    $plugin = $pluginRepo->getPluginByName(self::PLUGIN_NAME);
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

  public function isTestingEnabled(): bool
  {
    $log_key = self::LOG_KEY_TEST_MODE_OFF;
    $result = filter_var($this->getDryRun(), FILTER_VALIDATE_BOOLEAN);

    if ($result) {
      $log_key = self::LOG_KEY_TEST_MODE_ON;
    }

    /**
     * @var LoggerContract $logger
     */
    $logger = pluginApp(LoggerContract::class);
    $logger->info(
      TranslationHelper::getLoggerKey($log_key),
      [
        'additionalInfo' => [],
        'method' => __METHOD__
      ]
    );

    return $result;
  }
}
