<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\WarehouseSupplier;

/**
 * Class WarehouseSupplierRepository
 *
 * @FIXME Use Validator.
 *
 * @package Wayfair\Repositories
 */
class WarehouseSupplierRepository extends Repository {

  const LOG_KEY_QUERY_FAILED = 'warehouseSupplierQueryFailed';

  /**
   * @param array $data
   *
   * @return WarehouseSupplier
   */
  public function createMapping($data = []) {
    /**
     * @var DataBase $database
     */
    $database                              = pluginApp(DataBase::class);
    /** @var WarehouseSupplier */
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
  public function updateMapping($data = []) {

    $mappingData = [];

    try
    {
      /**
       * @var DataBase $database
       */
      $database                  = pluginApp(DataBase::class);
      $mappingData               = $database->query(WarehouseSupplier::class)->where('id', '=', $data['id'])->get();
    }
    catch (\Exception $e) {
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

    if (isset($mappingData) && !empty($mappingData) && isset($mappingData[0])) {
      $mappingDatum              = $mappingData[0];
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
  public function findByWarehouseId($warehouseId) {

    $mappingData = [];

    try
    {
      /**
       * @var DataBase $database
       */
      $database    = pluginApp(DataBase::class);
      $mappingData = $database->query(WarehouseSupplier::class)->where('warehouseId', '=', $warehouseId)->get();
    }
    catch (\Exception $e) {
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

    if (isset($mappingData) && !empty($mappingData) && isset($mappingData[0])) {
      $mappingDatum = $mappingData[0];

      return $mappingDatum;
    }

    return null;
  }

  /**
   * @param array $data
   *
   * @return void
   */
  public function deleteMapping($data = []) {

    if (isset($data['id'])) {
      $mappingData = [];

      try
      {
        /**
         * @var DataBase $database
         */
        $database                  = pluginApp(DataBase::class);
        $mappingData               = $database->query(WarehouseSupplier::class)->where('id', '=', $data['id'])->get();
      }
      catch (\Exception $e) {
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

      if (isset($mappingData) && !empty($mappingData) && isset($mappingData[0])) {
        $database->delete($mappingData[0]);
      }
    }
  }

  /**
   * @param array $data
   *
   * @return array
   */
  public function saveMappings($data = []) {
    $removedData = array_filter(
        $data, function ($datum) {
          return isset($datum['removed']);
        }
    );
    $createdData = array_filter(
        $data, function ($datum) {
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
  public function getAllMappings() {

    try
    {
      /**
       * @var DataBase $database
       */
      $database = pluginApp(DataBase::class);
      return $database->query(WarehouseSupplier::class)->get();
    }
    catch (\Exception $e) {
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
   * @param string $supplierId
   *
   * @return string
   */
  public function findBySupplierId(string $supplierId) {

    $mappingData = [];

    try
    {
      /**
       * @var DataBase $database
       */
      $database    = pluginApp(DataBase::class);
      $mappingData = $database->query(WarehouseSupplier::class)->where('supplierId', '=', $supplierId)->get();
    }
    catch (\Exception $e) {
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

    if (isset($mappingData) && !empty($mappingData) && isset($mappingData[0]))
    {
      return $mappingData[0]->warehouseId;
    }

   return '';
  }
}
