<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Plenty\Modules\Item\VariationStock\Contracts\VariationStockRepositoryContract;
use Plenty\Modules\Item\VariationStock\Models\VariationStock;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Repositories\WarehouseSupplierRepository;

class InventoryMapper
{
  const LOG_KEY_STOCK_MISSING_WAREHOUSE = 'stockMissingWarehouse';
  const LOG_KEY_NO_SUPPLIER_ID_ASSIGNED_TO_WAREHOUSE = 'noSupplierIDForWarehouse';
  const LOG_KEY_INVALID_INVENTORY_AMOUNT = 'invalidInventoryAmount';

  /**
   * @param $mainVariationId
   *
   * @return mixed
   */
  public function getAvailableDate($mainVariationId)
  {
    // TODO: verify behavior and add function description
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
   * Determine the "Quantity On Hand" to report to Wayfair,
   * based on a VariationStock
   * @param VariationStock $variationStock
   * @param AbstractConfigHelper $configHelper
   *
   * @return int|mixed
   */
  static function getQuantityOnHand($variationStock, $configHelper = null)
  {
    if (!isset($variationStock) || !isset($variationStock->netStock))
    {
      // API did not return a net stock
      // not a valid input for Wayfair, should get filtered out later.
      return null;
    }

    $netStock = $variationStock->netStock;
    
    if ($netStock <= -1)
    {
      // Wayfair doesn't understand values below -1
      return -1;
    }

    $stockBuffer = null;
    if (isset($configHelper))
    {
      $stockBuffer = $configHelper->getStockBufferValue();
    }

    if (!isset($stockBuffer))
    {
      // no buffer to apply
      return $netStock;
    }


    if ($stockBuffer < 0)
    {
      // invalid value for buffer
      // TODO: add warning log
      return $netStock;
    }

    if ($netStock > $stockBuffer)
    {
      // report stock, considering buffer
      return $netStock - $stockBuffer;
    }

    // stock is less than or equal buffer, so Wayfair should see this as no stock.
    return 0;
  }

  /**
   * Create Inventory DTOs for one variation.
   * Returns a DTO for each supplier ID that has stock information for the variation.
   * @param $variationData
   *
   * @return RequestDTO[]
   */
  public function createInventoryDTOsFromVariation($variationData)
  {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    /**@var AbstractConfigHelper $configHelper */
    $configHelper = pluginApp(AbstractConfigHelper::class);

    /** @var array<string,RequestDTO> $requestDtosBySuID */
    $requestDtosBySuID = [];

    $supplierPartNumber = $this->getSupplierPartNumberFromVariation($variationData);

    $mainVariationId = $variationData['id'];
    $nextAvailableDate = $this->getAvailableDate($mainVariationId); // Pending. Need Item

    $stockList = $variationData['stock'];
    /** @var VariationStock $stock */
    foreach ($stockList as $stock) {
      $warehouseId = $stock['warehouseId'];
      if (!isset($warehouseId)) {
        // we don't know the warehouse, so we can't figure out the supplier ID.
        // Not an error, but unexpected.
        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_STOCK_MISSING_WAREHOUSE),
          [
            'additionalInfo' => ['variationID' => $mainVariationId,],
            'method' => __METHOD__
          ]
        );
        continue;
      }

      // this is a 'mixed' value - might be null.
      $supplierId = $this->getSupplierIDForWarehouseID($warehouseId);

      if (!isset($supplierId) || $supplierId === 0 || $supplierId === '0') {
        // no supplier assigned to this warehouse - NOT an error - we should NOT sync it
        $loggerContract->debug(
          TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SUPPLIER_ID_ASSIGNED_TO_WAREHOUSE),
          [
            'additionalInfo' => ['warehouseID' => $warehouseId, 'variationID' => $mainVariationId,],
            'method' => __METHOD__
          ]
        );
        continue;
      }

      // Avl Immediately. Net Stock.
      $onHand = self::getQuantityOnHand($stock, $configHelper);

      if (null == $onHand)
      {
        // null value is NOT a valid input for quantity on hand - do NOT send to Wayfair.
        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_INVENTORY_AMOUNT),
          [
            'additionalInfo' => [
              'amount' => json_encode($onHand)
            ],
            'method' => __METHOD__
          ]
        );
        continue;
      }

      // Already Ordered items.
      $onOrder = $stock->reservedStock;

      // key for a potential preexisting DTO that we need to merge with
      $dtoKey = $supplierId . '_' . $supplierPartNumber;

      /** @var RequestDTO $existingDTO */
      $existingDTO = $requestDtosBySuID[$dtoKey];

      if (isset($existingDTO) && !empty($existingDTO))
      {
        /* merge with previous values for this suID.
         * stock values should be summed.
         * Variation-level data (nextAvailableDate) does not vary.
        */

        // all null $onHand values are thrown out before this calculation happens.
        $onHand = self::mergeInventoryQuantities($onHand, $existingDTO->getQuantityOnHand());

        // quantityOnOrder IS nullable.
        $onOrder = self::mergeInventoryQuantities($onOrder, $existingDTO->getQuantityOnOrder());
      }

      $dtoData = [
        'supplierId' => $supplierId,
        'supplierPartNumber' => $supplierPartNumber,
        'quantityOnHand' => $onHand,
        'quantityOnOrder' => $onOrder,
        'itemNextAvailabilityDate' => $nextAvailableDate,
        'productNameAndOptions' => $variationData['name']
      ];

      // replaces any existing DTO with a "merge" for this suID
      $requestDtosBySuID[$dtoKey] = RequestDTO::createFromArray($dtoData);
    }

    return array_values($requestDtosBySuID);
  }

  /**
   * Get the Wayfair Supplier ID for a Warehouse, by ID
   *
   * @param int $warehouseId
   * @return mixed
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

  /**
   * Merge two quantities for an inventory DTO
   * 
   * Note that -1 is a VALID input for the inventory APIs!
   * Note that this MAY return null
   *
   * @param [float] $left
   * @param [float] $right
   * @param [bool] $nullable
   * @return float|null
   */
  static function mergeInventoryQuantities($left, $right)
  {
    // protecting against values below -1
    if (null != $left && $left < -1)
    {
      $left = -1;
    }

    // protecting against values below -1
    if (null != $right && $right < -1)
    {
      $right = -1;
    }

    if (null == $left || $left <= 0 && $right != 0)
    {
      return $right;
    }

    if (null == $right || $right <= 0 && $left != 0)
    {
      return $left;
    }

    return $left + $right;
  }
}
