<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Wayfair\Core\Contracts\LoggerContract;
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
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    parent::handle();
  }
}
