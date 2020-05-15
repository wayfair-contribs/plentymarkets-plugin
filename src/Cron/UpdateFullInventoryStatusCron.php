<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

class UpdateFullInventoryStatusCron extends Cron {

  /**
   * @var KeyValueRepository
   */
  public $keyValueRepository;

  /**
   * UpdateFullInventoryStatusCron constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository)
  {
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    /**
     * @var LoggerContract $loggerContract
     */
    $loggerContract = pluginApp(LoggerContract::class);

    $loggerContract->debug(TranslationHelper::getLoggerKey('cronStartedMessage'), ['method' => __METHOD__]);
    try {
      $dt = $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_STATUS_UPDATED_AT);
      if (!$dt || (\time() - \strtotime($dt)) > 7200) {
        $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_CRON_STATUS, AbstractConfigHelper::FULL_INVENTORY_CRON_IDLE);
      }
    } finally {
      $loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), ['method' => __METHOD__]);
    }
  }
}
