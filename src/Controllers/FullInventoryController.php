<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Wayfair\Services\InventoryUpdateService;
use Wayfair\Services\InventoryStatusService;

class FullInventoryController extends Controller
{

  /**
   * @param InventoryUpdateService $fullInventoryService
   *
   * @return string
   * @throws \Exception
   */
  public function sync(InventoryUpdateService $inventoryUpdateService)
  {
    // set manual flag so that we know where sync request came from
    return \json_encode($inventoryUpdateService->sync(true));
  }

  /**
   * @param InventoryStatusService $statusService
   *
   * @return string
   */
  public function getState(InventoryStatusService $statusService)
  {
    return \json_encode($statusService->getServiceState(true));
  }
}
