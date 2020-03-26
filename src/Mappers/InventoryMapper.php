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

class InventoryMapper {

  /**
   * @param $mainVariationId
   *
   * @return mixed
   */
  public function getAvailableDate($mainVariationId) {
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
   * @param $stockList
   *
   * @return array
   */
  private function getStockFromWarehouse($stockList) {
    /**
     * @var WarehouseSupplierRepository $warehouseSupplierRepository
     */
    $warehouseSupplierRepository = pluginApp(WarehouseSupplierRepository::class);
    $supplierId = null;
    $warehouseId = null;
    foreach ($stockList as $stock) {
      $warehouseId = $stock['warehouseId'];
      if (isset($warehouseId)) {
        $warehouseSupplierMapping = $warehouseSupplierRepository->findByWarehouseId($warehouseId);
        $supplierId = isset($warehouseSupplierMapping) ? $warehouseSupplierMapping->supplierId : null;
        if (isset($supplierId)) {
          $stock['supplierId'] = $supplierId;

          return $stock;
        }
      }
    }

    return [];
  }

  /**
   * @param mixed $variationStock
   *
   * @return int|mixed
   */
  public function getNetStock($variationStock) {
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
   * @param $data
   *
   * @return RequestDTO
   */
  public function map($data) {
    /** @var KeyValueRepository $keyValueRepository */
    $keyValueRepository = pluginApp(KeyValueRepository::class);
    $itemMappingMethod = $keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);

    switch ($itemMappingMethod) {
      case AbstractConfigHelper::ITEM_MAPPING_SKU:
        $supplierPartNumber = $data['variationSkus'][0]['sku'];
        break;
      case AbstractConfigHelper::ITEM_MAPPING_EAN:
        $supplierPartNumber = $data['variationBarcodes'][0]['code'];
        break;
      default:
        $supplierPartNumber = $data['number'];
        break;
    }

    $mainVariationId = $data['id'];
    $nextAvailableDate = $this->getAvailableDate($mainVariationId); // Pending. Need Item
    $stock = $this->getStockFromWarehouse($data['stock']);
    $dtoData = [
        'supplierId' => isset($stock['supplierId']) ? $stock['supplierId'] : null,
        'supplierPartNumber' => $supplierPartNumber,
        'quantityOnHand' => isset($stock['netStock']) ? $stock['netStock'] : null, // Avl Immediately. Net Stock.
        'quantityOnOrder' => isset($stock['reservedStock']) ? $stock['reservedStock'] : null, // Already Ordered items.
        'itemNextAvailabilityDate' => $nextAvailableDate,
        'productNameAndOptions' => $data['name']
    ];

    return RequestDTO::createFromArray($dtoData);
  }
}