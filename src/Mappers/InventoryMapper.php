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
  const LOG_KEY_MAPPING_METHOD_LOOKUP = 'itemMappingMethodLookup';

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

    // FIXME: need to cactch Exceptions and ignore a single variation, not fail out!
    $supplierPartNumber = $this->getSupplierPartNumberFromVariation($variationData);

    $mainVariationId = $variationData['id'];
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
   * @return mixed
   */
  private function getSupplierPartNumberFromVariation($variationData)
  {

    // TODO: add a unit test around this method (if using pluginApp doesn't block that)

    /** @var KeyValueRepository $keyValueRepository */
    $keyValueRepository = pluginApp(KeyValueRepository::class);

    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    $itemMappingMethod = $keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);

    $loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_MAPPING_METHOD_LOOKUP),
      [
        'additionalInfo' => [
          'itemMappingMethodFound' => $itemMappingMethod,
        ],
        'method' => __METHOD__
      ]
    );

    $variationId = $variationData['id'];

    $variationNumber = $variationData['number'];

    switch ($itemMappingMethod) {
      case AbstractConfigHelper::ITEM_MAPPING_SKU:
        $skuVal = $variationData['variationSkus'][0]['sku'];
        if (!isset($skuVal) || empty($skuVal))
        {
          throw new \Exception("Variation with ID " . $variationId . " has no SKU value");
        }
        return $skuVal;
      case AbstractConfigHelper::ITEM_MAPPING_EAN:
        $barcodeVal = $variationData['variationBarcodes'][0]['code'];
        if (!isset($barcodeVal) || empty($barcodeVal))
        {
          throw new \Exception("Variation with ID " . $variationId . " has no barcode value");
        }
        return $barcodeVal;
      case AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER:
        break;
      default:
        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_UNDEFINED_MAPPING_METHOD),
          [
            'additionalInfo' => [
              'itemMappingMethodFound' => $itemMappingMethod,
              'defaultingTo' => AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
            ],
            'method' => __METHOD__
          ]
        );
        break;
    }

    if (!isset($variationNumber) || empty($variationNumber))
    {
      throw new \Exception("Variation with ID " . $variationId . " has no variation number");
    }

    return $variationNumber;
  }
}
