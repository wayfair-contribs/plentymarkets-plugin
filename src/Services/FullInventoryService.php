<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Repositories\KeyValueRepository;

class FullInventoryService
{
  const LOG_KEY_DEBUG = 'debugInventoryUpdate';
  const LOG_KEY_INVENTORY_UPDATE_ERROR = 'inventoryUpdateError';
  const LOG_KEY_SKIPPED = 'fullInventorySkipped';
  const LOG_KEY_START = 'fullInventoryStart';
  const LOG_KEY_END = 'fullInventoryEnd';

  /**
   * @var KeyValueRepository
   */
  public $keyValueRepository;

  /**
   * FullInventoryService constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository)
  {
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * @param bool $manual
   * @return array
   * @throws \Exception
   *
   */
  public function sync(bool $manual = false): array
  {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    try {
      $loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG), [
        'additionalInfo' => ['manual' => (string)$manual, 'message' => 'Checking if Full Inventory is currently running.'],
        'method' => __METHOD__
      ]);
      $status = 'Sync did not run';
      $cronStatus = $this->keyValueRepository->get(ConfigHelperContract::FULL_INVENTORY_CRON_STATUS);
      $lastRun = $this->keyValueRepository->get(ConfigHelperContract::FULL_INVENTORY_STATUS_UPDATED_AT);
      if ($cronStatus !== ConfigHelperContract::FULL_INVENTORY_CRON_RUNNING) {
        $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");
        $loggerContract->info(TranslationHelper::getLoggerKey(self::LOG_KEY_START), [
          'additionalInfo' => ['manual' => (string)$manual, 'lastRun' => $lastRun],
          'method' => __METHOD__
        ]);
        $result = null;
        try {
          $this->keyValueRepository->putOrReplace(ConfigHelperContract::FULL_INVENTORY_CRON_STATUS, ConfigHelperContract::FULL_INVENTORY_CRON_RUNNING);
          $inventoryUpdateService = pluginApp(InventoryUpdateService::class);
          $result = $inventoryUpdateService->sync(true);
          $status = 'OK';
        } catch (\Exception $e) {
          $loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_ERROR), [
            'additionalInfo' => ['manual' => (string)$manual, 'message' => $e->getMessage()],
            'method' => __METHOD__
          ]);
          $status = 'ERROR';
        } finally {
          $this->keyValueRepository->putOrReplace(ConfigHelperContract::FULL_INVENTORY_CRON_STATUS, ConfigHelperContract::FULL_INVENTORY_CRON_IDLE);

          $loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_END), [
            'additionalInfo' => ['result' => $result],
            'method' => __METHOD__
          ]);

          $externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");
        }
      } else {
        $status = 'Sync is already running';
        // FIXME: this should be indicated in UI - currently says "synced" when skipped
        $loggerContract->info(TranslationHelper::getLoggerKey(self::LOG_KEY_SKIPPED), [
          'additionalInfo' => ['manual' => (string)$manual],
          'method' => __METHOD__
        ]);

        $externalLogs->addErrorLog(($manual ? "Manual " : "Automatic") ."Full inventory sync BLOCKED - full inventory sync is currently running");
      }
      return ['status' => $status];
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }
}
