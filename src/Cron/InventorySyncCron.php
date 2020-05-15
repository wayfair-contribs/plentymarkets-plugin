<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryUpdateService;

class InventorySyncCron extends Cron
{

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
    /**
     * @var InventoryUpdateService $inventoryUpdateService
     */
    $inventoryUpdateService = pluginApp(InventoryUpdateService::class);
    try {
      $inventoryUpdateService->sync();
    } finally {
      $loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), ['method' => __METHOD__]);
    }
  }
}
