<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\FullInventoryService;

class InventoryFullCron extends Cron {

  /**
   * @var FullInventoryService
   */
  public $fullInventoryService;

  /**
   * InventoryFullCron constructor.
   *
   * @param FullInventoryService $fullInventoryService
   */
  public function __construct(FullInventoryService $fullInventoryService)
  {
    $this->fullInventoryService = $fullInventoryService;
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
      $this->fullInventoryService->sync();
    } finally {
      $loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), ['method' => __METHOD__]);
    }
  }
}
