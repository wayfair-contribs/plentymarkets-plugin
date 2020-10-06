<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\CarrierScac;

/**
 * Carrier Scac code repository.
 * Class CarrierScacRepository
 *
 * @package Wayfair\Repositories
 */
class CarrierScacRepository extends Repository
{

  const LOG_KEY_QUERY_FAILED = "scacCodeQueryFailed";

  /**
   * Create a CarrierScac mapping
   *
   * TODO: move this to a factory
   *
   * @return CarrierScac
   */
  public function create(array $data)
  {
    /** @var CarrierScac $model */
    $model            = pluginApp(CarrierScac::class);
    $model->carrierId = $data['carrierId'];
    $model->scac      = $data['scac'];
    $model->createdAt = time();

    $this->database->save($model);

    return $model;
  }

  /**
   * List all carrier scac code mappings.
   *
   * @return array
   * @throws \Exception
   */
  public function findAll()
  {

    try {
      return $this->database->query(CarrierScac::class)->get();
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
   * @param int $id
   *
   * @return CarrierScac|null
   */
  public function findById(int $id)
  {

    try {
      $model = $this->database->query(CarrierScac::class)->where('id', '=', $id)->get();

      if (!(empty($model) || empty($model[0]))) {
        return $model[0];
      }
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
            'referenceType' => 'scacId',
            'referenceValue' => $id,
            'method' => __METHOD__
          ]
        );
    }

    return null;
  }


  /**
   * Find Scac code by PM carrier id / shipping provider ID
   *
   * @param int $carrierId
   *
   * @return string |null
   */
  public function findScacByCarrierId(int $carrierId)
  {
    try {
      $model = $this->database->query(CarrierScac::class)->where('carrierId', '=', $carrierId)->get();
      if (!(empty($model) || empty($model[0]))) {
        return $model[0]->scac;
      }
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
            'referenceType' => 'carrierId',
            'referenceValue' => $carrierId,
            'method' => __METHOD__
          ]
        );
    }

    return null;
  }


  /**
   * @param array $data
   */
  public function save(array $data)
  {
    $models = $this->findAll();
    /** @var CarrierScac $model */
    foreach ($models as $model) {
      $this->database->delete($model);
    }

    foreach ($data as $datum) {
      $this->create($datum);
    }
  }

  /**
   * @param array $data
   *
   * @return CarrierScac|null
   */
  public function update(array $data)
  {
    $model = $this->findById($data['id']);
    if (!empty($model)) {
      $model->carrierId = $data['carrierId'];
      $model->scac      = $data['scac'];
      $model->createdAt = time();

      $this->database->save($model);

      return $model;
    }

    return null;
  }

  /**
   * @param array $data
   */
  public function delete(array $data)
  {
    if (!empty($data['id'])) {
      $model = $this->findById($data['id']);
      if (!empty($model)) {
        $this->database->delete($model);
      }
    }
  }
}
