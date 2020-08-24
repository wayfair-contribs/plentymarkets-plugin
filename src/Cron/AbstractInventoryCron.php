<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryUpdateService;

abstract class AbstractInventoryCron extends CronHandler
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
    $syncResult = [];
    try {
      /** @var InventoryUpdateService */
      $service = pluginApp(InventoryUpdateService::class);
      $syncResult = $service->sync($this->full);
    } finally {
      $loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), [
        'additionalInfo' => [
          'full' => $this->full,
          'result' => $syncResult
        ],
        'method' => __METHOD__
      ]);
    }
  }
}
