<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;
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
  const LOG_KEY_NORMALIZING_INVENTORY = 'normalizingInventoryAmount';

  const VARIATION_COL_ID = 'id';
  const VARIATION_COL_NUMBER = 'number';
  const VARIATION_COL_BARCODES = 'variationBarcodes';
  const VARIATION_COL_SKUS = 'variationSkus';

  const STOCK_COL_STOCK_NET = 'stockNet';
  const STOCK_COL_VARIATION_ID = 'variationId';
  const STOCK_COL_WAREHOUSE_ID = 'warehouseId';
  const STOCK_COL_RESERVED_STOCK = 'reservedStock';

  const STOCK_FILTER_UPDATED_AT_FROM = 'updatedAtFrom';
  const STOCK_FILTER_UPDATED_AT_TO = 'updatedAtTo';

  const ALL_STOCK_COLS = [
    self::STOCK_COL_WAREHOUSE_ID,
    self::STOCK_COL_VARIATION_ID,
    self::STOCK_COL_STOCK_NET,
    self::STOCK_COL_RESERVED_STOCK
  ];

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
   * Determine the "Quantity On Hand" to report to Wayfair
   * @param int $netStock
   * @param int $variationId
   * @param LoggerContract $loggerContract
   *
   * @return int|mixed
   */
  static function normalizeQuantityOnHand($netStock, $variationId, $loggerContract = null)
  {
    if (!isset($netStock)) {
      // API did not return a net stock
      // not a valid input for Wayfair, should get filtered out later.
      return null;
    }

    if ($netStock <= -1) {
      if (isset($loggerContract)) {

        $loggerContract->info(
          TranslationHelper::getLoggerKey(self::LOG_KEY_NORMALIZING_INVENTORY),
          [
            'additionalInfo' => [
              'variationId' => $variationId,
              'originalNetStock' => $netStock
            ],
            'method' => __METHOD__
          ]
        );
      }

      // Wayfair doesn't understand values below -1
      return -1;
    }

    return $netStock;
  }

  /**
   * Create Inventory DTOs for one variation.
   * Returns a DTO for each supplier ID that has stock information for the variation,
   * fitting in the constraints of the arguments
   *
   * @param array $variationData
   * @param string $itemMappingMethod
   * @param int $stockBuffer
   * @param string $w3cStart
   * @param string $w3CEnd
   *
   * @return RequestDTO[]
   */
  public function createInventoryDTOsFromVariation($variationData, $itemMappingMethod, $stockBuffer, $w3cStart = null, $w3CEnd = null)
  {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    /** @var array<string,RequestDTO> $requestDtosBySuID */
    $requestDtosBySuID = [];

    $mainVariationId = $variationData[self::VARIATION_COL_ID];
    $variationNumber = $variationData[self::VARIATION_COL_NUMBER];

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

    $filters = [self::STOCK_COL_VARIATION_ID => $mainVariationId];
    if (isset($w3cStart) && !empty($w3cStart)) {
      // FIXME: these conversions should happen before calling into here
      $filters[self::STOCK_FILTER_UPDATED_AT_FROM] = $w3cStart;

      if (isset($w3CEnd) && !empty($w3CEnd)) {
        $filters[self::STOCK_FILTER_UPDATED_AT_TO] = $w3CEnd;
      }
    }

    /**
     * @var StockRepositoryContract
     */
    $stockRepository = pluginApp(StockRepositoryContract::class);
    $stockRepository->setFilters($filters);

    $pageNumber = 1;
    do {
      $stockSearchResponsePage = $stockRepository->listStock(InventoryMapper::ALL_STOCK_COLS, $pageNumber, 50);

      foreach ($stockSearchResponsePage->getResult() as $stock) {
        $warehouseId = $stock[InventoryMapper::STOCK_COL_WAREHOUSE_ID];

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

        // TODO: cache results of this lookup?
        $supplierId = $this->getSupplierIDForWarehouseID($warehouseId);

        if (!isset($supplierId) || $supplierId === 0 || $supplierId === '0') {
          // no supplier assigned to this warehouse - NOT an error - we should NOT sync it
          $loggerContract->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SUPPLIER_ID_ASSIGNED_TO_WAREHOUSE),
            [
              'additionalInfo' => [
                'warehouseID' => $warehouseId,
                'variationID' => $mainVariationId
              ],
              'method' => __METHOD__
            ]
          );
          continue;
        }

        // Avl Immediately. ADJUSTED Net Stock (see Stock Buffer setting in Wayfair plugin).
        $onHand = self::normalizeQuantityOnHand($stock[InventoryMapper::STOCK_COL_STOCK_NET], $loggerContract);

        if (!isset($onHand) || ($onHand < -1)) {
          // inventory amounts less than -1 are not accepted - do NOT send to Wayfair.
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
        $onOrder = $stock[self::STOCK_COL_RESERVED_STOCK];

        // key for a potential preexisting DTO that we need to merge with
        $dtoKey = $supplierId . '_' . $supplierPartNumber;

        /** @var RequestDTO $existingDTO */
        $existingDTO = $requestDtosBySuID[$dtoKey];

        if (isset($existingDTO) && !empty($existingDTO)) {
          /*
            *merge with previous values for this suID:
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
      } // end of loop over stock items on page
      $pageNumber++;
    } while (!$stockSearchResponsePage->isLastPage());

    // all stock amounts are now visited
    $inventoryDTOs = array_values($requestDtosBySuID);

    // apply the stock buffer setting to each DTO sent to Wayfair, not to each warehouse
    foreach ($inventoryDTOs as $idx => $oneDTO) {
      $dtos[$idx] = self::applyStockBuffer($oneDTO, $stockBuffer);
    }

    return $inventoryDTOs;
  }

  /**
   * Apply stock buffer value to the DTO
   *
   * @param RequestDTO $dto
   * @return RequestDTO
   */
  static function applyStockBuffer($dto, $stockBuffer)
  {
    if (!isset($dto) || !isset($stockBuffer) || $stockBuffer <= 0) {
      // no buffer to apply
      return $dto;
    }

    $netStock = $dto->getQuantityOnHand();

    if (!isset($netStock) || $netStock < 0) {
      // preserve negative stock value
      return $dto;
    }

    $netStock -= $stockBuffer;

    if ($netStock < 0) {
      // normalize to zero because stock was not negative before applying buffer
      $netStock = 0;
    }

    $dto->setQuantityOnHand($netStock);

    return $dto;
  }

  /**
   * Get the Wayfair Supplier ID for a Warehouse, by ID
   *
   * @param int $warehouseId
   * @return mixed
   */
  private static function getSupplierIDForWarehouseID($warehouseId)
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
    if (!isset($variationData)) {
      return null;
    }

    $supplierPartNumber = null;

    $variationNumber = $variationData[self::VARIATION_COL_NUMBER];

    $supplierPartNumber = null;

    switch ($itemMappingMethod) {
      case AbstractConfigHelper::ITEM_MAPPING_SKU:
        if (array_key_exists(self::VARIATION_COL_SKUS, $variationData) && !empty($variationData[self::VARIATION_COL_SKUS])) {
          // FIXME: should not blindly use the first SKU - only should use the one assigned to Wayfair!
          $supplierPartNumber = $variationData[self::VARIATION_COL_SKUS][0]['sku'];
        }
        break;
      case AbstractConfigHelper::ITEM_MAPPING_EAN:
        if (array_key_exists(self::VARIATION_COL_BARCODES, $variationData) && !empty($variationData[self::VARIATION_COL_BARCODES])) {
          // TODO: find a way to avoid blindly using first barcode.
          $supplierPartNumber = $variationData[self::VARIATION_COL_BARCODES][0]['code'];
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
    if (isset($right) && $right < -1) {
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
