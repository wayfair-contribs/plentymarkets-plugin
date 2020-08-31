<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Services\InventoryUpdateService;

class FullInventoryCron extends InventoryCron
{

  /**
   * FullInventoryCron constructor.
   *
   * @param bool $full
   */
  public function __construct(
    InventoryUpdateservice $inventoryUpdateService,
    LoggerContract $loggerContract
  ) {
    parent::__construct($inventoryUpdateService, $loggerContract, true);
  }
}
