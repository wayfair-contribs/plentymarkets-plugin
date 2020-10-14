<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use InvalidArgumentException;
use Plenty\Modules\Item\VariationStock\Contracts\VariationStockRepositoryContract;
use Plenty\Modules\StockManagement\Stock\Contracts\StockRepositoryContract;
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

  const SKU_COL_MARKET_ID = 'marketId';
  const SKU_COL_SKU = 'sku';

  const STOCK_FILTER_UPDATED_AT_FROM = 'updatedAtFrom';
  const STOCK_FILTER_UPDATED_AT_TO = 'updatedAtTo';

  const ALL_STOCK_COLS = [
    self::STOCK_COL_WAREHOUSE_ID,
    self::STOCK_COL_VARIATION_ID,
    self::STOCK_COL_STOCK_NET,
    self::STOCK_COL_RESERVED_STOCK
  ];

  /** @var LoggerContract */
  private $logger;

  public function __construct(
    LoggerContract $logger
  ) {
    $this->logger = $logger;
  }

  /**
   * TODO: verify behavior and add function description
   * FIXME: should be calculated at the Wayfair Product level, not the Plentymarkets Variation level!
   *
   * @param int $variationId
   *
   * @return mixed
   */
  public function getAvailableDate($variationId)
  {
    /** @var $VariationStockRepositoryContract */
    $variationStockRepositoryContract = pluginApp(VariationStockRepositoryContract::class);
    $variationStockMovements = $variationStockRepositoryContract->listStockMovements($variationId, ['processRowType', 'bestBeforeDate'], 1, 50);
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
   *
   * @return int|mixed
   */
  static function normalizeQuantityOnHand($netStock)
  {
    if (!isset($netStock)) {
      // API did not return a net stock
      // not a valid input for Wayfair, should get filtered out later.
      return null;
    }

    if ($netStock <= -1) {

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
   * @param array $variationData the Variation data from the Variation Repository
   * @param string $itemMappingMethod the item mapping method setting
   * @param float|null $referrerId (optional) the referrer ID for Wayfair, for use with SKU mapping method
   * @param int|null $stockBuffer (optional) amount of stock (per product) to withold from Wayfair
   * @param string|null $timeWindowStartW3c (optional) start of time-based filter in W3C format
   * @param string|null $timeWindowEndW3c (optional) end of time-based filter in W3C format
   *
   * @return RequestDTO[]
   */
  public function createInventoryDTOsFromVariation(
    $variationData,
    $itemMappingMethod,
    $referrerId = null,
    $stockBuffer = null,
    $timeWindowStartW3c = null,
    $timeWindowEndW3c = null
  ) {
    if (!isset($variationData) || empty($variationData)) {
      throw new InvalidArgumentException("No Variation data provided");
    }

    $variationId = null;
    if (key_exists(self::VARIATION_COL_ID, $variationData)) {
      $variationId = $variationData[self::VARIATION_COL_ID];
    }

    if (!isset($variationId) || empty($variationId)) {
      throw new InvalidArgumentException('ID is a required field for Variation');
    }

    $variationNumber = null;
    if (key_exists(self::VARIATION_COL_NUMBER, $variationData)) {
      $variationNumber = $variationData[self::VARIATION_COL_NUMBER];
    }

    if (!isset($variationNumber) || empty($variationNumber)) {
      throw new InvalidArgumentException('Variation Number is a required field for Variation');
    }

    $supplierPartNumber = null;
    $partNumberFailureMessage = 'product number data is missing from Variation';

    try {
      $supplierPartNumber = $this->getSupplierPartNumberFromVariation($variationData, $itemMappingMethod, $referrerId, $this->logger);
    } catch (\Exception $e) {
      $partNumberFailureMessage = get_class($e) . ' : ' . $e->getMessage();
    }

    if (!isset($supplierPartNumber) || empty($supplierPartNumber)) {
      $this->logger->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_PART_NUMBER_MISSING),
        [
          'additionalInfo' => [
            'variationID' => $variationId,
            'variationNumber' => $variationNumber,
            'itemMappingMethod' => $itemMappingMethod,
            'reason' => $partNumberFailureMessage
          ],
          'method' => __METHOD__
        ]
      );
      // inventory is worthless without part numbers
      return [];
    }

    $nextAvailableDate = $this->getAvailableDate($variationId); // Pending. Need Item

    $filters = [self::STOCK_COL_VARIATION_ID => $variationId];

    if (isset($timeWindowStartW3c) && !empty($timeWindowStartW3c)) {
      $filters[self::STOCK_FILTER_UPDATED_AT_FROM] = $timeWindowStartW3c;

      if (isset($timeWindowEndW3c) && !empty($timeWindowEndW3c)) {
        $filters[self::STOCK_FILTER_UPDATED_AT_TO] = $timeWindowEndW3c;
      }
    }

    $stockRepository= pluginApp(StockRepositoryContract::class);
    $stockRepository->setFilters($filters);

    /** @var array<string,RequestDTO> $requestDtosBySuID */
    $requestDtosBySuID = [];

    $pageNumber = 1;
    do {
      $stockSearchResponsePage = $stockRepository->listStock(InventoryMapper::ALL_STOCK_COLS, $pageNumber, 50);

      foreach ($stockSearchResponsePage->getResult() as $stock) {
        $warehouseId = $stock[InventoryMapper::STOCK_COL_WAREHOUSE_ID];

        if (!isset($warehouseId)) {
          // we don't know the warehouse, so we can't figure out the supplier ID.
          $this->logger->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_STOCK_MISSING_WAREHOUSE),
            [
              'additionalInfo' => [
                'variationID' => $variationId,
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
          // no supplier assigned to this warehouse - NOT an error
          // we should NOT send this stock to Wayfair
          $this->logger->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SUPPLIER_ID_ASSIGNED_TO_WAREHOUSE),
            [
              'additionalInfo' => [
                'warehouseID' => $warehouseId,
                'variationID' => $variationId
              ],
              'method' => __METHOD__
            ]
          );
          continue;
        }

        // Avl Immediately. ADJUSTED Net Stock (see Stock Buffer setting in Wayfair plugin).
        $originalStockNet = $stock[InventoryMapper::STOCK_COL_STOCK_NET];
        $onHand = self::normalizeQuantityOnHand($originalStockNet);

        if ($originalStockNet != $onHand) {
          $this->logger->info(
            TranslationHelper::getLoggerKey(self::LOG_KEY_NORMALIZING_INVENTORY),
            [
              'additionalInfo' => [
                'variationId' => $variationId,
                'originalStockNet' => $originalStockNet
              ],
              'method' => __METHOD__
            ]
          );
        }

        if (!isset($onHand) || ($onHand < -1)) {
          // inventory amounts less than -1 are not accepted - do NOT send to Wayfair.
          $this->logger->warning(
            TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_INVENTORY_AMOUNT),
            [
              'additionalInfo' => [
                'variationID' => $variationId,
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
          $onHand = $this->mergeInventoryQuantities($onHand, $existingDTO->getQuantityOnHand());

          // quantityOnOrder IS nullable.
          $onOrder = $this->mergeInventoryQuantities($onOrder, $existingDTO->getQuantityOnOrder());
        }

        /** @var RequestDTO */
        $requestDto = pluginApp(RequestDTO::class);

        $requestDto->setSupplierId($supplierId);
        $requestDto->setSupplierPartNumber($supplierPartNumber);
        $requestDto->setQuantityOnHand($onHand);
        $requestDto->setQuantityOnOrder($onOrder);
        $requestDto->setItemNextAvailabilityDate($nextAvailableDate);
        $requestDto->setProductNameAndOptions($variationData['name']);

        // replaces any existing DTO with a "merge" for this suID
        $requestDtosBySuID[$dtoKey] = $requestDto;
      } // end of loop over stock items on page
      $pageNumber++;
    } while (!$stockSearchResponsePage->isLastPage());

    // all stock amounts are now visited
    $inventoryDTOs = array_values($requestDtosBySuID);

    // apply the stock buffer setting to each DTO sent to Wayfair, not to each warehouse
    if (isset($stockBuffer) && $stockBuffer > 0) {
      foreach ($inventoryDTOs as $idx => $oneDTO) {
        $dtos[$idx] = $this->applyStockBuffer($oneDTO, $stockBuffer);
      }
    }

    return $inventoryDTOs;
  }

  /**
   * Apply stock buffer value to the DTO
   *
   * @param RequestDTO $dto
   * @return RequestDTO
   */
  function applyStockBuffer($dto, $stockBuffer)
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
  private function getSupplierIDForWarehouseID($warehouseId)
  {
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
   * @param float|null $referrerId (optional) the referrer ID for Wayfair, for use with SKU mapping method
   * @param LoggerContract|null $logger
   * @return mixed
   * @throws \Exception
   */
  function getSupplierPartNumberFromVariation($variationData, $itemMappingMethod, $referrerId = null, $logger = null)
  {
    if (!isset($variationData) || empty($variationData)) {
      throw new InvalidArgumentException("Variation data is not set");
    }

    $variationNumber = $variationData[self::VARIATION_COL_NUMBER];

    switch ($itemMappingMethod) {
      case AbstractConfigHelper::ITEM_MAPPING_SKU:
        if (array_key_exists(self::VARIATION_COL_SKUS, $variationData) && !empty($variationData[self::VARIATION_COL_SKUS])) {

          $allSkus = $variationData[self::VARIATION_COL_SKUS];
          if (isset($referrerId) && $referrerId > 0) {
            foreach ($allSkus as $variationSku) {
              if (array_key_exists(self::SKU_COL_MARKET_ID, $variationSku)) {
                $skuReferrer = $variationSku[self::SKU_COL_MARKET_ID];

                if ($referrerId == $skuReferrer) {
                  return $variationSku[self::SKU_COL_SKU];
                }
              }
            }
          }

          // fall-back to original behavior of using first SKU
          return $allSkus[0]['sku'];
        }

        throw new \Exception("No SKUs found");

      case AbstractConfigHelper::ITEM_MAPPING_EAN:
        if (array_key_exists(self::VARIATION_COL_BARCODES, $variationData) && !empty($variationData[self::VARIATION_COL_BARCODES])) {
          // TODO: find a way to avoid blindly using first barcode.
          return $variationData[self::VARIATION_COL_BARCODES][0]['code'];
        }

        throw new \Exception("No Barcodes found");
      case AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER:
        // variation number is always set - enforced by Plenty UI
        return $variationNumber;
      default:
        // just in case - ConfigHelper should have validated the method value
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

        return $variationNumber;
    }
  }

  /**
   * Merge two quantities for an inventory DTO
   *
   * Note that -1 is a VALID input for the inventory APIs!
   * Note that this MAY return null
   *
   * @param float $left
   * @param float $right
   * @param bool $nullable
   * @return float|null
   */
  function mergeInventoryQuantities($left, $right)
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
