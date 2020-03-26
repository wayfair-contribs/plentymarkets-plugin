<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\PendingLogs;

class PendingLogsRepository extends Repository {

  const LOG_KEY_QUERY_FAILED = 'pendingLogsQueryFailed';

  /**
   * @return PendingLogs[]
   */
  public function getAll(): array {
    try
    {
      $database = pluginApp(DataBase::class);
        return $database->query(PendingLogs::class)
          ->offset(0)
          ->limit(800)
          ->get();
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
   * @param array $ids
   *
   * @return bool
   */
  public function delete(array $ids): bool {
    try
    {
      $database = pluginApp(DataBase::class);
      return $database->query(PendingLogs::class)->whereIn('id', $ids)->delete();
    }
    catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'ids' => json_encode($ids),
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
  public function deleteAll(): bool {
    try
    {
      $database = pluginApp(DataBase::class);
      return $database->query(PendingLogs::class)->delete();
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

  }
}
