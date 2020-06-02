<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Plenty\Modules\Item\VariationStock\Contracts\VariationStockRepositoryContract;
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
  const LOG_KEY_UNDEFINED_MAPPING_METHOD = 'undefinedMappingMethod';
  const LOG_KEY_PART_NUMBER_LOOKUP = 'partNumberLookup';
  const LOG_KEY_PART_NUMBER_MISSING = 'partNumberMissing';

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
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    /** @var RequestDTO[] $inventoryDTOs */
    $inventoryDTOs = [];

    $mainVariationId = $variationData['id'];
    $variationNumber = $variationData['number'];

    $supplierPartNumber = $this->getSupplierPartNumberFromVariation($variationData);

    if (!isset($supplierPartNumber) || empty($supplierPartNumber)) {
      $itemMappingMethod = $this->getItemMappingMode();

      $loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_PART_NUMBER_MISSING),
        [
          'additionalInfo' => [
            'variationID' => $mainVariationId,
            'variationNumber' => $variationNumber,
            'itemMappingMethod' => $itemMappingMethod
          ],
          'method' => __METHOD__
        ]
      );

      // inventory is worthless without part numbers
      return [];
    }

    $nextAvailableDate = $this->getAvailableDate($mainVariationId); // Pending. Need Item

    $stockList = $variationData['stock'];
    foreach ($stockList as $stock) {
      $warehouseId = $stock['warehouseId'];
      if (!isset($warehouseId)) {
        // we don't know the warehouse, so we can't figure out the supplier ID.
        // Not an error, but unexpected.
        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_STOCK_MISSING_WAREHOUSE),
          [
            'additionalInfo' => [
              'variationID' => $mainVariationId,
              'variationNumber' => $variationNumber
            ],
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
   * @param LoggerContract $logger
   * @return mixed
   */
  private function getSupplierPartNumberFromVariation($variationData, $logger = null)
  {
    // TODO: add a unit test around this method

    $supplierPartNumber = null;

    $mainVariationId = $variationData['id'];
    $variationNumber = $variationData['number'];

    $itemMappingMethod = $itemMappingMethod = $this->getItemMappingMode();

    $supplierPartNumber = $variationNumber;

    try {

      switch ($itemMappingMethod) {
        case AbstractConfigHelper::ITEM_MAPPING_SKU:
          $supplierPartNumber = $variationData['variationSkus'][0]['sku'];
          break;
        case AbstractConfigHelper::ITEM_MAPPING_EAN:
          $supplierPartNumber = $variationData['variationBarcodes'][0]['code'];
          break;
        case AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER:
          // already set to variationNumber
          break;
        default:
          if (isset($logger)) {
            $logger->warning(
              TranslationHelper::getLoggerKey(self::LOG_KEY_UNDEFINED_MAPPING_METHOD),
              [
                'additionalInfo' => [
                  'itemMappingMethodFound' => $itemMappingMethod,
                  'defaultingTo' => AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
                ],
                'method' => __METHOD__
              ]
            );
          }
      }

      return $supplierPartNumber;
    } finally {
      if (isset($logger)) {
        $logger->debug(
          TranslationHelper::getLoggerKey(self::LOG_KEY_PART_NUMBER_LOOKUP),
          [
            'additionalInfo' => [
              'itemMappingMethod' => $itemMappingMethod,
              'variationID' => $mainVariationId,
              'variationNumber' => $variationNumber,
              'resolvedPartNumber' => $supplierPartNumber
            ],
            'method' => __METHOD__
          ]
        );
      }
    }
  }

  /**
   * Wrapper for item mapping mode lookup
   *
   * @return string
   */
  private function getItemMappingMode()
  {
    /** @var KeyValueRepository $keyValueRepository */
    $keyValueRepository = pluginApp(KeyValueRepository::class);
    return $keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);
  }
}
