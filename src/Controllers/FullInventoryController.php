<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Wayfair\Services\ScheduledInventorySyncService;
use Wayfair\Services\InventoryStatusService;

class FullInventoryController extends Controller
{

  /**
   * @param ScheduledInventorySyncService $fullInventoryService
   *
   * @return string
   * @throws \Exception
   */
  public function sync(ScheduledInventorySyncService $fullInventoryService)
  {
    // set manual flag so that we know where sync request came from
    return \json_encode($fullInventoryService->sync(true));
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
