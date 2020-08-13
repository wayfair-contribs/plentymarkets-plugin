<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\ScheduledInventorySyncService;

abstract class InventoryCron extends Cron
{
  /** @var bool */
  private $full;

  /**
   * InventoryCron constructor.
   *
   * @param bool $full
   */
  public function __construct(bool $full)
  {
    $this->full = $full;
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
      /** @var ScheduledInventorySyncService */
      $service = pluginApp(ScheduledInventorySyncService::class);
      $service->sync($this->full);
    } finally {
      $loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), ['method' => __METHOD__]);
    }
  }
}
