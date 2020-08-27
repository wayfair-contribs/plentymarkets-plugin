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
   * @param InventoryUpdateService $inventoryUpdateService
   * @param InventoryStatusService $inventoryStatusService
   *
   * @return string
   * @throws \Exception
   */
  public function sync(InventoryUpdateService $inventoryUpdateService, InventoryStatusService $statusService)
  {
    $inventoryUpdateService->sync(true, true);
    // set manual flag so that we know where sync request came from
    return $this->getState($statusService);
  }

  /**
   * @param InventoryStatusService $statusService
   *
   * @return string
   */
  public function getState(InventoryStatusService $statusService)
  {
    return json_encode($statusService->getServiceState(true));
  }

}
