<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Wayfair\Services\FullInventoryService;
use Wayfair\Services\FullInventoryStatusService;

class FullInventoryController extends Controller
{

  /**
   * @param FullInventoryService $fullInventoryService
   *
   * @return string
   * @throws \Exception
   */
  public function sync(FullInventoryService $fullInventoryService)
  {
    // set manual flag so that we know where sync request came from
    return \json_encode($fullInventoryService->sync(true));
  }

  public function getState(FullInventoryStatusService $statusService)
  {
    return \json_encode($statusService->getServiceState());
  }
}
