<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryUpdateService;

abstract class InventoryCron extends CronHandler
{

  /** @var InventoryUpdateService */
  private $inventoryUpdateService;

  /** @var  */
  private $loggerContract;

  /** @var bool */
  private $full;

  /**
   * InventoryCron constructor.
   *
   * @param bool $full
   */
  public function __construct(
    InventoryUpdateservice $inventoryUpdateService,
    LoggerContract $loggerContract,
    bool $full = false
  ) {
    $this->inventoryUpdateService = $inventoryUpdateService;
    $this->loggerContract = $loggerContract;
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
    $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronStartedMessage'), [
      'additionalInfo' => [
        'full' => $this->full,
      ],
      'method' => __METHOD__
    ]);
    $syncResult = [];
    try {
      $syncResult = $this->inventoryUpdateService->sync($this->full);
    } finally {
      $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), [
        'additionalInfo' => [
          'full' => $this->full,
          'result' => $syncResult
        ],
        'method' => __METHOD__
      ]);
    }
  }
}
