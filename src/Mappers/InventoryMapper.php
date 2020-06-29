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
use Wayfair\Repositories\WarehouseSupplierRepository;

class InventoryMapper
{
  const LOG_KEY_STOCK_MISSING_WAREHOUSE = 'stockMissingWarehouse';
  const LOG_KEY_NO_SUPPLIER_ID_ASSIGNED_TO_WAREHOUSE = 'noSupplierIDForWarehouse';
  const LOG_KEY_UNDEFINED_MAPPING_METHOD = 'undefinedMappingMethod';
  const LOG_KEY_PART_NUMBER_LOOKUP = 'partNumberLookup';
  const LOG_KEY_PART_NUMBER_MISSING = 'partNumberMissing';
  const LOG_KEY_INVALID_INVENTORY_AMOUNT = 'invalidInventoryAmount';
  const LOG_KEY_INVALID_STOCK_BUFFER = 'invalidStockBufferValue';

  const VARIATION_BARCODES = 'variationBarcodes';
  const VARIATION_SKUS = 'variationSkus';

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
   * @param LoggerContract $loggerContract
   *
   * @return int|mixed
   */
  static function getQuantityOnHand($variationStock, $configHelper = null, $loggerContract = null)
  {
    if (!isset($variationStock) || !isset($variationStock->netStock)) {
      // API did not return a net stock
      // not a valid input for Wayfair, should get filtered out later.
      return null;
    }

    $netStock = $variationStock->netStock;

    if ($netStock <= -1) {
      // Wayfair doesn't understand values below -1
      return -1;
    }

    $stockBuffer = null;
    if (isset($configHelper)) {
      $stockBuffer = $configHelper->getStockBufferValue();
    }

    if (!isset($stockBuffer)) {
      // no buffer to apply
      return $netStock;
    }


    if ($stockBuffer < 0) {
      // invalid value for buffer

      if (isset($loggerContract)) {
        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_STOCK_BUFFER),
          [
            'additionalInfo' => ['stockBuffer' => $stockBuffer],
            'method' => __METHOD__
          ]
        );
      }

      return $netStock;
    }

    if ($netStock > $stockBuffer) {
      // report stock, considering buffer
      return $netStock - $stockBuffer;
    }

    // stock is less than or equal buffer, so Wayfair should see this as no stock.
    return 0;
  }

  /**
   * Create Inventory DTOs for one variation.
   * Returns a DTO for each supplier ID that has stock information for the variation.
   * @param array $variationData
   * @param string $itemMappingMethod
   *
   * @return RequestDTO[]
   */
  public function createInventoryDTOsFromVariation($variationData, $itemMappingMethod)
  {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    /**@var AbstractConfigHelper $configHelper */
    $configHelper = pluginApp(AbstractConfigHelper::class);

    /** @var array<string,RequestDTO> $requestDtosBySuID */
    $requestDtosBySuID = [];

    $mainVariationId = $variationData['id'];
    $variationNumber = $variationData['number'];

    $supplierPartNumber = $this->getSupplierPartNumberFromVariation($variationData, $itemMappingMethod, $loggerContract);

    if (!isset($supplierPartNumber) || empty($supplierPartNumber)) {

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

    // the 'stock' element is not declared for the Variation type,
    // so type hints for "variationData" need to stay as "array"
    $stockList = $variationData['stock'];
    /** @var VariationStock $stock */
    foreach ($stockList as $stock) {
      $warehouseId = $stock['warehouseId'];
      if (!isset($warehouseId)) {
        // we don't know the warehouse, so we can't figure out the supplier ID.
        // Not an error, but unexpected.
        $loggerContract->info(
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

      // Avl Immediately. ADJUSTED Net Stock (see Stock Buffer setting in Wayfair plugin).
      $onHand = self::getQuantityOnHand($stock, $configHelper, $loggerContract);

      if (!isset($onHand)) {
        // null value is NOT a valid input for quantity on hand - do NOT send to Wayfair.
        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_INVENTORY_AMOUNT),
          [
            'additionalInfo' => [
              'variationID' => $mainVariationId,
              'variationNumber' => $variationNumber,
              'warehouseId' => $warehouseId,
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

      if (isset($existingDTO) && !empty($existingDTO)) {
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
   * @param string $itemMappingMethod
   * @param LoggerContract $logger
   * @return mixed
   */
  static function getSupplierPartNumberFromVariation($variationData, $itemMappingMethod, $logger = null)
  {
    if (!isset($variationData))
    {
      return null;
    }

    $supplierPartNumber = null;

    $mainVariationId = $variationData['id'];
    $variationNumber = $variationData['number'];

    $supplierPartNumber = null;

    try {

      switch ($itemMappingMethod) {
        case AbstractConfigHelper::ITEM_MAPPING_SKU:
          if (array_key_exists(self::VARIATION_SKUS, $variationData) && !empty($variationData[self::VARIATION_SKUS]))
          {
            $supplierPartNumber = $variationData[self::VARIATION_SKUS][0]['sku'];
          }
          break;
        case AbstractConfigHelper::ITEM_MAPPING_EAN:
          if (array_key_exists(self::VARIATION_BARCODES, $variationData) && !empty($variationData[self::VARIATION_BARCODES]))
          {
            $supplierPartNumber = $variationData[self::VARIATION_BARCODES][0]['code'];
          }
          break;
        case AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER:
          $supplierPartNumber = $variationNumber;
          break;
        default:
          // just in case - ConfigHelper should have validated the method value
          $supplierPartNumber = $variationNumber;
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
    if (isset($left) && $left < -1) {
      $left = -1;
    }

    // protecting against values below -1
    if (isset($left) && $right < -1) {
      $right = -1;
    }

    if (!isset($left) || $left <= 0 && $right != 0) {
      return $right;
    }

    if (!isset($right) || $right <= 0 && $left != 0) {
      return $left;
    }

    return $left + $right;
  }
}
