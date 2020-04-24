<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Services\FullInventoryService;
use Wayfair\Services\InventoryUpdateService;
use Plenty\Plugin\Http\Request;

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

  /**
   * @param KeyValueRepository $keyValueRepository
   *
   * @return string
   * @throws \Exception
   */
  public function syncTest(Request $request, KeyValueRepository $keyValueRepository)
  {
    $cronStatus = $keyValueRepository->get(ConfigHelperContract::FULL_INVENTORY_CRON_STATUS);
    if ($cronStatus !== ConfigHelperContract::FULL_INVENTORY_CRON_RUNNING) {
      $keyValueRepository->putOrReplace(ConfigHelperContract::FULL_INVENTORY_CRON_STATUS, ConfigHelperContract::FULL_INVENTORY_CRON_RUNNING);
      $inventoryUpdateService = pluginApp(InventoryUpdateService::class);
      $data = $inventoryUpdateService->sync(true);
      $keyValueRepository->putOrReplace(ConfigHelperContract::FULL_INVENTORY_CRON_STATUS, ConfigHelperContract::FULL_INVENTORY_CRON_IDLE);

      return $request->input('data') == 1 ? json_encode($data) : json_encode(['error' => 'Sync is running']);
    }

    return json_encode(['error' => 'Sync is running']);
  }
}
