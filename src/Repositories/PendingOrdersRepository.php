<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\PendingOrders;

class PendingOrdersRepository extends Repository
{
  const MAX_ATTEMPTS = 5;
  const GET_LIMIT = 50;

  const LOG_KEY_QUERY_FAILED = 'pendingOrdersQueryFailed';

  /**
   * @param array $data
   *
   * @return bool
   */
  public function insert(array $data): bool
  {
    if (!isset($data['poNum']) || empty($data['poNum']) || !isset($data['items']) || !count($data['items'])) {
      return false;
    }
    $database = pluginApp(DataBase::class);
    $model = pluginApp(PendingOrders::class);
    $model->poNum = $data['poNum'];
    $model->items = \json_encode($data['items'], true);
    $model->attempts = 0;
    $database->save($model);
    return true;
  }

  /**
   * @param string $poNum
   */
  public function incrementAttempts(string $poNum)
  {
    $data = [];

    try {
      $database = pluginApp(DataBase::class);
      $data = $database->query(PendingOrders::class)->where('poNum', '=', $poNum)
          ->limit(1)
          ->get();
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
            'referenceType' => 'poNum',
            'referenceValue' => $poNum,
            'method' => __METHOD__
          ]
        );
    }

    if (empty($data)) {
      return;
    }

    $model = $data[0];

    if (isset($model) && $model->attempts == self::MAX_ATTEMPTS) {
      $database->delete($model);
      return;
    }

    $model->attempts += 1;
    $database->save($model);
  }

  /**
   * @param string $poNum
   *
   * @return array|null
   */
  public function get(string $poNum)
  {
    
    try {
      $database = pluginApp(DataBase::class);
      $model = $database->query(PendingOrders::class)->where('poNum', '=', $poNum)
          ->limit(1)
          ->get();

      if (isset($model) && !empty($model) && isset($model[0])) {
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
            'referenceType' => 'poNum',
            'referenceValue' => $poNum,
            'method' => __METHOD__
          ]
        );
    }
    return null;
  }

  /**
   * @param int $circle
   *
   * @return PendingOrders[]
   */
  public function getAll(int $circle): array
  {
    try {
      $database = pluginApp(DataBase::class);
      return $database->query(PendingOrders::class)
          ->offset(self::GET_LIMIT * ($circle - 1))
          ->limit(self::GET_LIMIT)
          ->get();
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
   * @param array $poNums
   *
   * @return bool
   */
  public function delete(array $poNums): bool
  {
    try {
      $database = pluginApp(DataBase::class);
      return $database->query(PendingOrders::class)->whereIn('poNum', $poNums)->delete();
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'poNums' => json_encode($poNums),
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'method' => __METHOD__
          ]
        );
    }

    return false;
  }

  /**
   * @return bool
   */
  public function deleteAll()
  {
    try {
      $database = pluginApp(DataBase::class);
      return $database->query(PendingOrders::class)->delete();
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
  }
}
