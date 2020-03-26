<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Helpers;

use Plenty\Modules\Plugin\Contracts\PluginRepositoryContract;
use Plenty\Plugin\CachingRepository;
use Plenty\Plugin\ConfigRepository;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Repositories\KeyValueRepository;

class ConfigHelper extends AbstractConfigHelper {

  const CACHING_MINUTES = 360;
  const INTEGRATION_AGENT_NAME = 'PlentyMarket';
  /**
   * @var ConfigRepository
   */
  protected $config;

  /**
   * ConfigHelper constructor.
   *
   * @param ConfigRepository $config
   */
  public function __construct(ConfigRepository $config) {
    $this->config = $config;
  }

  /**
   * @return mixed
   */
  public function getClientId() {
    return $this->config->get(self::PLUGIN_NAME . '.global.container.clientId');
  }

  /**
   * @return mixed
   */
  public function getClientSecret() {
    return $this->config->get(self::PLUGIN_NAME . '.global.container.clientSecret');
  }

  /**
   * @return int
   */
  public function getOrderReferrerValue(): int {
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
    $value = (int)$keyValueRepository->get(self::SETTINGS_ORDER_REFERRER_KEY);
    $cachingRepository->put(self::SETTINGS_ORDER_REFERRER_KEY, $value, self::CACHING_MINUTES);
    return $value;
  }

  /**
   * @return int|mixed
   */
  public function getStockBufferValue() {
    /**
     * @var KeyValueRepository $keyValueRepository
     */
    $keyValueRepository = pluginApp(KeyValueRepository::class);

    return (int)$keyValueRepository->get(self::SETTINGS_ORDER_REFERRER_KEY);
  }

  public function getDryRun(): string {
    return $this->config->get(self::PLUGIN_NAME . '.global.container.dryRunMode');
  }

  public function isAllItemsActive(): bool {
    /**
     * @var KeyValueRepository $keyValueRepository
     */
    $keyValueRepository = pluginApp(KeyValueRepository::class);

    return (bool)$keyValueRepository->get(self::SETTINGS_SEND_ALL_ITEMS_KEY);
  }

  /**
   * @return string
   */
  public function getImportOrderSince() {
    /**
     * @var KeyValueRepository $keyValueRepository
     */
    $keyValueRepository = pluginApp(KeyValueRepository::class);

    return $keyValueRepository->get(self::IMPORT_ORDER_SINCE);
  }

  /**
   * @return string
   */
  public function getPluginVersion() {
    $pluginRepo = pluginApp(PluginRepositoryContract::class);
    $plugin = $pluginRepo->getPluginByName(AbstractConfigHelper::PLUGIN_NAME);
    $plugin = $pluginRepo->decoratePlugin($plugin);
    return $plugin->versionProductive;
  }

  /**
   * @return string
   */
  public function getIntegrationAgentHeader() {
    return self::INTEGRATION_AGENT_NAME . ' - v:' . $this->getPluginVersion();
  }

  public function isTestingEnabled(): bool {
    return filter_var($this->getDryRun(), FILTER_VALIDATE_BOOLEAN);
  }

}
