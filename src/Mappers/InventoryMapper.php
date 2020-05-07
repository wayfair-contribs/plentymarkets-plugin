<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Plenty\Modules\Item\VariationStock\Contracts\VariationStockRepositoryContract;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Repositories\WarehouseSupplierRepository;

class InventoryMapper
{

  /**
   * @param $mainVariationId
   *
   * @return mixed
   */
  public function getAvailableDate($mainVariationId)
  {
    /**
     * @var VariationStockRepositoryContract $variationStockRepositoryContract
     */
    $variationStockRepositoryContract = pluginApp(VariationStockRepositoryContract::class);
    $variationStockMovements = $variationStockRepositoryContract->listStockMovements($mainVariationId, ['processRowType', 'bestBeforeDate'], 1, 50);
    $bestBeforeDateForIncomingStock = null;
    foreach ($variationStockMovements as $variationStockMovement) {
      if ($variationStockMovement['processRowType'] === 1) {
        $bestBeforeDateForIncomingStock = $variationStockMovement['bestBeforeDate'];
      }
    }
    return $bestBeforeDateForIncomingStock;
  }

  /**
   * @param mixed $variationStock
   *
   * @return int|mixed
   */
  public function getNetStock($variationStock)
  {
    /**
     * @var AbstractConfigHelper $configHelper
     */
    $configHelper = pluginApp(AbstractConfigHelper::class);
    $stockBuffer = $configHelper->getStockBufferValue();
    if ($variationStock->netStock > $stockBuffer) {
      return $variationStock->netStock - $stockBuffer;
    } else {
      return 0;
    }
  }

  /**
   * Create an Inventory DTO for each Warehouse in the Variation data
   * @param $variationData
   *
   * @return RequestDTO[]
   */
  public function createInventoryDTOsFromVariation($variationData)
  {

    /** @var RequestDTO[] $inventoryDTOs */
    $inventoryDTOs = [];

    $supplierPartNumber = $this->getSupplierPartNumberFromVariation($variationData);

    $mainVariationId = $variationData['id'];
    $nextAvailableDate = $this->getAvailableDate($mainVariationId); // Pending. Need Item

    $stockList = $variationData['stock'];
    foreach ($stockList as $stock) {
      $warehouseId = $stock['warehouseId'];
      if (!isset($warehouseId)) {
        // we don't know the warehouse, so we can't figure out the supplier ID. Not an error.
        // TODO: add a log
        continue;
      }

      $supplierId = $this->getSupplierIDForWarehouseID($warehouseId);

      if (!isset($supplierId) || $supplierId <= 0) {
        // no supplier assigned to this warehouse - NOT an error.
        // TODO: add a log
        continue;
      }

      $dtoData = [
        'supplierId' => $supplierId,
        'supplierPartNumber' => $supplierPartNumber,
        'quantityOnHand' => isset($stock['netStock']) ? $stock['netStock'] : null, // Avl Immediately. Net Stock.
        'quantityOnOrder' => isset($stock['reservedStock']) ? $stock['reservedStock'] : null, // Already Ordered items.
        'itemNextAvailabilityDate' => $nextAvailableDate,
        'productNameAndOptions' => $variationData['name']
      ];

      $inventoryDTOs[] = RequestDTO::createFromArray($dtoData);
    }

    return $inventoryDTOs;
  }

  /**
   * Get the Wayfair Supplier ID for a Warehouse, by ID
   *
   * @param int $warehouseId
   * @return int|null
   */
  private function getSupplierIDForWarehouseID($warehouseId)
  {
    /**
     * @var WarehouseSupplierRepository $warehouseSupplierRepository
     */
    $warehouseSupplierRepository = pluginApp(WarehouseSupplierRepository::class);

    $warehouseSupplierMapping = $warehouseSupplierRepository->findByWarehouseId($warehouseId);

    if (isset($warehouseSupplierMapping)) {
      return $warehouseSupplierMapping->supplierId;
    }

    return null;
  }

  /**
   * Get the supplier's part number from Plentymarkets Variation data
   *
   * @param array $variationData
   * @return mixed
   */
  private function getSupplierPartNumberFromVariation($variationData)
  {
    /** @var KeyValueRepository $keyValueRepository */
    $keyValueRepository = pluginApp(KeyValueRepository::class);
    $itemMappingMethod = $keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);

    switch ($itemMappingMethod) {
      case AbstractConfigHelper::ITEM_MAPPING_SKU:
        return $variationData['variationSkus'][0]['sku'];
      case AbstractConfigHelper::ITEM_MAPPING_EAN:
        return $variationData['variationBarcodes'][0]['code'];
      case AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER:
      default:
        return $variationData['number'];
    }
  }
}
