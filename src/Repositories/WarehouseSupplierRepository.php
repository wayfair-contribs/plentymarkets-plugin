<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\StockManagement\Warehouse\Contracts\WarehouseRepositoryContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\WarehouseSupplier;

/**
 * Class WarehouseSupplierRepository
 *
 * @FIXME Use Validator.
 *
 * @package Wayfair\Repositories
 */
class WarehouseSupplierRepository extends Repository
{

  const LOG_KEY_QUERY_FAILED = 'warehouseSupplierQueryFailed';
  const LOG_KEY_WAREHOUSE_ID_INVALID = 'warehouseIdInvalid';

  /**
   * @param array $data
   *
   * @return WarehouseSupplier
   */
  public function createMapping($data = [])
  {
    /**
     * @var DataBase $database
     */
    $database                              = pluginApp(DataBase::class);
    $warehouseSupplierMapping              = pluginApp(WarehouseSupplier::class);
    $warehouseSupplierMapping->supplierId  = $data['supplierId'];
    $warehouseSupplierMapping->warehouseId = $data['warehouseId'];
    $warehouseSupplierMapping->createdAt   = time();
    $database->save($warehouseSupplierMapping);

    return $warehouseSupplierMapping;
  }

  /**
   * @param array $data
   *
   * @return mixed
   */
  public function updateMapping($data = [])
  {

    $results = [];

    try {
      /**
       * @var DataBase $database
       */
      $database                  = pluginApp(DataBase::class);
      $results               = $database->query(WarehouseSupplier::class)->where('id', '=', $data['id'])->get();
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'referenceType' => 'id',
            'referenceValue' => $data['id'],
            'method' => __METHOD__
          ]
        );
    }

    if (isset($results) && !empty($results) && isset($results[0])) {
      $mappingDatum              = $results[0];
      $mappingDatum->supplierId  = $data['supplierId'];
      $mappingDatum->warehouseId = $data['warehouseId'];
      $mappingDatum->createdAt   = time();
      $database->save($mappingDatum);
      return $mappingDatum;
    }
  }

  /**
   * @param mixed $warehouseId
   *
   * @return mixed|null
   */
  public function findByWarehouseId($warehouseId)
  {

    $results = [];

    try {
      /**
       * @var DataBase $database
       */
      $database    = pluginApp(DataBase::class);
      $results = $database->query(WarehouseSupplier::class)->where('warehouseId', '=', $warehouseId)->get();
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'referenceType' => 'warehouseId',
            'referenceValue' => $warehouseId,
            'method' => __METHOD__
          ]
        );
    }

    if (isset($results) && !empty($results) && isset($results[0])) {
      $mappingDatum = $results[0];

      return $mappingDatum;
    }

    return null;
  }

  /**
   * @param array $data
   *
   * @return void
   */
  public function deleteMapping($data = [])
  {

    if (isset($data['id'])) {
      $results = [];

      try {
        /**
         * @var DataBase $database
         */
        $database                  = pluginApp(DataBase::class);
        $results               = $database->query(WarehouseSupplier::class)->where('id', '=', $data['id'])->get();
      } catch (\Exception $e) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
            [
              'additionalInfo' => [
                'exception' => $e,
                'message' => $e->getMessage(),
                'stacktrace' => $e->getTrace()
              ],
              'referenceType' => 'id',
              'referenceValue' => $data['id'],
              'method' => __METHOD__
            ]
          );
      }

      if (isset($results) && !empty($results) && isset($results[0])) {
        $database->delete($results[0]);
      }
    }
  }

  /**
   * @param array $data
   *
   * @return array
   */
  public function saveMappings($data = [])
  {
    $removedData = array_filter(
      $data,
      function ($datum) {
        return isset($datum['removed']);
      }
    );
    $createdData = array_filter(
      $data,
      function ($datum) {
        return !isset($datum['removed']);
      }
    );
    $data = array_merge($removedData, $createdData);
    foreach ($data as $datum) {
      if (isset($datum['removed']) && $datum['removed']) {
        $this->deleteMapping($datum);
      } else {
        if (isset($datum['id'])) {
          $this->updateMapping($datum);
        } else {
          $this->createMapping($datum);
        }
      }
    }

    return $this->getAllMappings();
  }

  /**
   * @return mixed
   */
  public function getAllMappings()
  {

    try {
      /**
       * @var DataBase $database
       */
      $database = pluginApp(DataBase::class);
      return $database->query(WarehouseSupplier::class)->get();
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'method' => __METHOD__
          ]
        );
    }

    return [];
  }

  /**
   * Get the Plentymarkets instance IDs for all Warehouses matching the inputs
   *
   * TODO: add more parameters in order to choose the best warehouse.
   *  - Warehouse should have a positive stock amount for the item on order
   *  - see https://github.com/wayfair-contribs/plentymarkets-plugin/issues/92
   *
   * @param string $supplierId
   *
   * @return array
   */
  public function findWarehouseIds(string $supplierId)
  {
    $results = [];

    try {
      /**
       * @var DataBase $database
       */
      $database    = pluginApp(DataBase::class);
      $results = $database->query(WarehouseSupplier::class)->where('supplierId', '=', $supplierId)->get();
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'referenceType' => 'supplierId',
            'referenceValue' => $supplierId,
            'method' => __METHOD__
          ]
        );
    }

    if (!isset($results) || empty($results)) {
      return [];
    }

    $warehouseIdentifiers = [];

    /** @var WarehouseSupplier $mapping */
    foreach ($results as $key => $mapping) {
      $warehouseId = $mapping->warehouseId;

      if (!($warehouseId && $warehouseId > 0 && $this->warehouseExists($warehouseId))) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_WAREHOUSE_ID_INVALID),
            [
              'additionalInfo' => [
                'warehouseId' => $warehouseId
              ],
              'referenceType' => 'warehouseId',
              'referenceValue' => $warehouseId,
              'method' => __METHOD__
            ]
          );

        continue;
      }

      $warehouseIdentifiers[] = $warehouseId;
    }

    return $warehouseIdentifiers;
  }

  /**
   * check if the warehouse ID is valid
   *
   * @param int $warehouseId
   * @return boolean
   */
  function warehouseExists(int $warehouseId): bool
  {
    /** @var WarehouseRepositoryContract */
    $warehouseRepository = pluginApp(WarehouseRepositoryContract::class);

    $warehouse = $warehouseRepository->findById($warehouseId);

    // use presence of warehouse name to mean it really exists
    return isset($warehouse) && isset($warehouse->name) && !empty($warehouse->name);
  }
}
