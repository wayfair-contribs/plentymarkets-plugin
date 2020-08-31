<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Services\InventoryUpdateService;
use Wayfair\Services\InventoryStatusService;

class FullInventoryController extends InventoryController
{

  /**
   * FullInventoryController constructor
   *
   * @param InventoryUpdateService $inventoryUpdateService
   * @param InventoryStatusService $inventoryStatusService
   * @param LoggerContract $logger
   */
  public function __construct(
    InventoryUpdateService $inventoryUpdateService,
    InventoryStatusService $inventoryStatusService,
    LoggerContract $logger
  ) {
    parent::__construct($inventoryUpdateService, $inventoryStatusService, $logger, true);
  }
}
