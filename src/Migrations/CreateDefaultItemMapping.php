<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Set default item mapping method to Variation Number ('numberExact').
 * Class CreateDefaultItemMapping
 *
 * @package Wayfair\Migrations
 */
class CreateDefaultItemMapping
{

  const LOG_KEY_SETTING_MAPPING_METHOD = "settingMappingMethod";

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * CreateDefaultItemMapping constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository)
  {
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   *
   * @throws \Plenty\Exceptions\ValidationException
   * @return void
   */
  public function run()
  {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    $currentSetting = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);

    if (!isset($currentSetting) || empty($currentSetting)) {
      $loggerContract->warning(
        TranslationHelper::getLoggerKey(self::LOG_KEY_SETTING_MAPPING_METHOD),
        [
          'additionalInfo' => ['mappingMethod' => AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,],
          'method' => __METHOD__
        ]
      );

      $this->keyValueRepository->putOrReplace(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD, AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER);
    }
  }
}
