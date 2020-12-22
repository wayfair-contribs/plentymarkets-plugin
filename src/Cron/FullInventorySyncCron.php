<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryUpdateService;

class FullInventorySyncCron extends InventorySyncCron
{
  /**
   * InventorySyncCron constructor.
   *
   */
  public function __construct(
    InventoryUpdateservice $inventoryUpdateService,
    LoggerContract $loggerContract
  ) {
    parent::__construct(true, $inventoryUpdateService, $loggerContract);
  }

  /**
   * Call parent's handler,
   * but create log entries with this class name for filtering
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    $this->loggerContract->info(TranslationHelper::getLoggerKey('cronStartedMessage'), [
      'additionalInfo' => [
        'full' => $this->fullInventory
      ],
      'method' => __METHOD__
    ]);

    try {
      parent::handle();
    } finally {
      $this->loggerContract->info(TranslationHelper::getLoggerKey('cronFinishedMessage'), [
        'additionalInfo' => [
        ],
        'method' => __METHOD__
      ]);
    }
  }
}
