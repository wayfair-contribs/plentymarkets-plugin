<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Modules\Item\DataLayer\Contracts\ItemDataLayerRepositoryContract;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Plenty\Plugin\Http\Request;
use Wayfair\Core\Api\Services\InventoryService;
use Wayfair\Services\InventoryStatusService;
use Wayfair\Services\InventoryUpdateService;

class InventoryController
{

    /**
     * @param InventoryService $inventoryService
     *
     * @return mixed
     */
    public function fetch(InventoryService $inventoryService)
    {
        $fetched = $inventoryService->fetch();
        if (isset($fetched)) {
            return $fetched->getBody();
        }

        return [];
    }

    /**
     * @param Request $request
     *
     * @return false|string
     */
    public function filtered(Request $request)
    {
        $data = $request->input('data');
        /**
         * @var VariationSearchRepositoryContract $variationSearchRepositoryContract
         */
        $variationSearchRepositoryContract = pluginApp(VariationSearchRepositoryContract::class);
        $variationSearchRepositoryContract->setFilters(
            [
                'referrerId' => $data,
            ]
        );
        $variationSearchRepositoryContract->setSearchParams(
            [
                'with' => [
                    'item' => null
                ]
            ]
        );
        $result = $variationSearchRepositoryContract->search();

        // FIXME: result object is only a single 100-item PAGE. Need to iterate over paginated results.
        return json_encode($result->getResult());
    }

    /**
     * @param Request $request
     *
     * @return false|string
     */
    public function filtered1(Request $request)
    {
        $data = $request->input('data');
        /**
         * @var ItemDataLayerRepositoryContract $itemDataLayerRepository
         */
        $itemDataLayerRepository = pluginApp(ItemDataLayerRepositoryContract::class);
        $resultFields = [
            'itemBase' => [
                'id'
            ],
            'variationBase' => [
                'id',
                'customNumber'
            ],

            'variationStock' => [
                'params' => [
                    'type' => 'physical'
                ],
                'fields' => [
                    'stockNet',
                    'reservedStock',
                    'warehouseId'
                ]
            ],
            'variationLinkMarketplace' => [
                'marketplaceId'
            ],
            'variationWarehouseList' => [
                'variationId',
                'warehouseId',
            ],
            'itemDescription' => [
                'name1'
            ]
        ];
        $filters = [
            'variationStock.wasUpdatedBetween' => [
                'timestampFrom' => time() - 4800,
                'timestampTo' => time(),
            ],
            'variationVisibility.isVisibleForMarketplace' => [
                'mandatoryAllMarketplace' => [$data],
                'mandatoryOneMarketplace' => []
            ]
        ];
        $result2 = $itemDataLayerRepository->search($resultFields, $filters);

        return json_encode(
            [
                'result' => $result2->toArray()
            ]
        );
    }

    /**
     * @param Request $request
     *
     * @return false|string
     */
    public function getItem(Request $request)
    {
        $data = $request->input('data');
        /**
         * @var ItemRepositoryContract $itemRepositoryContract
         */
        $itemRepositoryContract = pluginApp(ItemRepositoryContract::class);
        $item = $itemRepositoryContract->show($data);

        return json_encode($item);
    }

     /**
   * @param InventoryUpdateService $inventoryUpdateService
   * @param InventoryStatusService $inventoryStatusService
   *
   * @return string
   * @throws \Exception
   */
  public function sync(InventoryUpdateService $inventoryUpdateService, InventoryStatusService $statusService)
  {
    $inventoryUpdateService->sync(false, true);
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
    return \json_encode($statusService->getServiceState(false));
  }
}
