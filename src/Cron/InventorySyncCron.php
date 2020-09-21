<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryStatusService;
use Wayfair\Services\InventoryUpdateService;

class InventorySyncCron extends Cron
{
  // amount of time between full syncs.
  // slightly less than one day.
  const MAX_SEC_BETWEEN_FULL_SYNCS = 86000;

  /** @var InventoryUpdateService */
  private $inventoryUpdateService;

  /** @var InventoryStatusService */
  private $inventoryStatusService;

  /** @var LoggerContract */
  private $loggerContract;

  /**
   * InventorySyncCron constructor.
   *
   */
  public function __construct(
    InventoryUpdateservice $inventoryUpdateService,
    InventoryStatusService $inventoryStatusService,
    LoggerContract $loggerContract
  ) {
    $this->inventoryUpdateService = $inventoryUpdateService;
    $this->inventoryStatusService = $inventoryStatusService;
    $this->loggerContract = $loggerContract;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronStartedMessage'), [
      'method' => __METHOD__
    ]);
    $syncResult = [];
    $fullInventory = false;
    try {
      $fullInventory = $this->isFullDue();
      $syncResult = $this->inventoryUpdateService->sync($fullInventory);
    } finally {
      $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), [
        'additionalInfo' => [
          'full' => $fullInventory,
          'result' => $syncResult
        ],
        'method' => __METHOD__
      ]);
    }
  }

  /**
   * Check if the inventory sync should happen for all inventory, or just recent inventory changes.
   *
   * @return bool
   */
  private function isFullDue(): bool
  {
    $lastFullInventorySuccessStart = $this->inventoryStatusService->getLastCompletionStart(true);

    if (!isset($lastFullInventorySuccessStart) || empty($lastFullInventorySuccessStart)) {
      return true;
    }

    $numericStartTime = strtotime($lastFullInventorySuccessStart);

    return ($numericStartTime <= 0 || time() >= $numericStartTime + self::MAX_SEC_BETWEEN_FULL_SYNCS);
  }
}
