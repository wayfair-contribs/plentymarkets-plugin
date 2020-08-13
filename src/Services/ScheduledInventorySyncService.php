<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;

/**
 * Service module for performing inventory synchronizations
 *
 * The service may be invoked in the following ways:
 * - Cron job
 * - Button in UI
 */
class ScheduledInventorySyncService
{
  const LOG_KEY_DEBUG = 'debugInventoryUpdate';
  const LOG_KEY_INVENTORY_UPDATE_ERROR = 'inventoryUpdateError';
  const LOG_KEY_SKIPPED_FULL = 'fullInventorySkipped';
  const LOG_KEY_LONG_RUN_FULL = `fullInventoryLongRunning`;
  const LOG_KEY_SKIPPED_PARTIAL= 'partialInventorySkipped';
  const LOG_KEY_LONG_RUN_PARTIAL = `partialInventoryLongRunning`;

  // TODO: make this user-configurable in a future update
  const MAX_INVENTORY_TIME_FULL = 7200;
  const MAX_INVENTORY_TIME_PARTIAL = self::MAX_INVENTORY_TIME_FULL;

  /**
   * @var InventoryUpdateService
   */
  private $inventoryUpdateService;

  /**
   * @var InventoryStatusService
   */
  private $statusService;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * InventorySyncService constructor.
   *
   * @param InventoryUpdateService $inventoryUpdateService
   * @param InventoryStatusService $statusService
   * @param LoggerContract $logger
   */
  public function __construct(
    InventoryUpdateService $inventoryUpdateService,
    InventoryStatusService $statusService,
    LoggerContract $logger
  ) {
    $this->inventoryUpdateService = $inventoryUpdateService;
    $this->statusService = $statusService;
    $this->logger = $logger;
  }

  /**
   * @param bool $manual
   * @return array
   * @throws \Exception
   *
   */
  public function sync(bool $full, bool $manual = false): array
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    try {
      // potential race conditions - change service management strategy in a future update
      // (but this is better than letting the old UpdateFullInventoryStatusCron randomly change service states)
      if ($this->statusService->isInventoryRunning($full) && !$this->serviceHasBeenRunningTooLong($full)) {
        $lastStartTime = $this->statusService->getLastAttemptTime($full);
        $stateArray = $this->statusService->getServiceState($full);

        $logKey = self::LOG_KEY_SKIPPED_PARTIAL;
        if ($full)
        {
          $logKey = self::LOG_KEY_SKIPPED_FULL;
        }

        $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
          'additionalInfo' => ['manual' => (string) $manual, 'startedAt' => $lastStartTime, 'state' => $stateArray],
          'method' => __METHOD__
        ]);
        $externalLogs->addErrorLog(($manual ? "Manual " : "Automatic") . "Inventory sync BLOCKED - already running");

        // early exit
        return $stateArray;
      }

      try {
        $this->statusService->markInventoryStarted(true, $manual);

        $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic ") . ($full ? "Full " : "Partial"). "inventory sync.");

        $syncResultDetails = $this->inventoryUpdateService->sync(true);

        // potential race conditions - change service management strategy in a future update
        if (self::syncResultIndicatesFailure($syncResultDetails)) {
          $this->statusService->markInventoryFailed(true, $manual);
        } else {
          $this->statusService->markInventoryComplete(true, $manual);
        }
      } catch (\Exception $e) {
        $this->statusService->markInventoryFailed(true, $manual, $e);
      }

      return $this->statusService->getServiceState(true);
    } finally {
      $externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic ") . ($full ? "Full " : "Partial"). "inventory sync.");

      /** @var LogSenderService $logSenderService */
      $logSenderService = pluginApp(LogSenderService::class);
      $logSenderService->execute($externalLogs->getLogs());
    }
  }

  /**
   * Check the results of calling the sync service
   *
   * @param array $syncResultDetails
   * @return boolean
   */
  public static function syncResultIndicatesFailure($syncResultDetails): bool
  {
    if (!isset($syncResultDetails) || empty($syncResultDetails)) {
      return true;
    }

    $numFailures = $syncResultDetails[InventoryUpdateService::INVENTORY_SAVE_FAIL];
    $syncError = $syncResultDetails[InventoryUpdateService::ERROR_MESSAGE];

    return (isset($syncError) && !empty($syncError)) || (isset($numFailures) && $numFailures > 0);
  }

  /**
   * Check if the state of the Inventory Service has been "running" for more than the maximum allotted time
   * This functionality was extracted from the old UpdateFullInventoryStatusCron
   *
   * @return boolean
   */
  private function serviceHasBeenRunningTooLong(bool $full): bool
  {
    $maxTime = self::MAX_INVENTORY_TIME_PARTIAL;
    $logKey = self::LOG_KEY_LONG_RUN_PARTIAL;
    if ($full)
    {
      $maxTime = self::MAX_INVENTORY_TIME_FULL;
      $logKey = self::LOG_KEY_LONG_RUN_FULL;
    }

    if ($this->statusService->isInventoryRunning($full)) {
      $lastStateChange = $this->statusService->getStateChangeTime(true);
      if (!$lastStateChange || (\time() - \strtotime($lastStateChange)) > $maxTime) {

        $this->logger->warning(TranslationHelper::getLoggerKey($logKey), [
          'additionalInfo' => ['lastStateChange' => $lastStateChange, 'maximumTime' => $maxTime],
          'method' => __METHOD__
        ]);
        return true;
      }
    }

    return false;
  }
}
