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
 * Service module for performing a Full Inventory sync or checking the status
 *
 * The service may be invoked in the following ways:
 * - Cron job
 * - Button in UI
 */
class FullInventoryService
{
  const LOG_KEY_DEBUG = 'debugInventoryUpdate';
  const LOG_KEY_INVENTORY_UPDATE_ERROR = 'inventoryUpdateError';
  const LOG_KEY_SKIPPED = 'fullInventorySkipped';
  const LOG_KEY_LONG_RUN = "fullInventoryLongRunning";

  // TODO: make this user-configurable in a future update
  const MAX_FULL_INVENTORY_TIME = 7200;

  /**
   * @var InventoryUpdateService
   */
  private $inventoryUpdateService;

  /**
   * @var FullInventoryStatusService
   */
  private $statusService;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * FullInventoryService constructor.
   *
   * @param InventoryUpdateService $inventoryUpdateService
   * @param FullInventoryStatusService $statusService
   * @param LoggerContract $logger
   */
  public function __construct(
    InventoryUpdateService $inventoryUpdateService,
    FullInventoryStatusService $statusService,
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
  public function sync(bool $manual = false): array
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    try {
      // potential race conditions - change service management strategy in a future update
      // (but this is better than letting the old UpdateFullInventoryStatusCron randomly change service states)
      if ($this->statusService->isFullInventoryRunning() && !$this->serviceHasBeenRunningTooLong()) {
        $lastStartTime = $this->statusService->getLastAttemptTime();
        $stateArray = $this->statusService->getServiceState();
        $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_SKIPPED), [
          'additionalInfo' => ['manual' => (string) $manual, 'startedAt' => $lastStartTime, 'state' => $stateArray],
          'method' => __METHOD__
        ]);
        $externalLogs->addErrorLog(($manual ? "Manual " : "Automatic") . "Full inventory sync BLOCKED - full inventory sync is currently running");

        // early exit
        return $stateArray;
      }

      try {
        $this->statusService->markFullInventoryStarted($manual);

        $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");

        $syncResultDetails = $this->inventoryUpdateService->sync(true);

        // potential race conditions - change service management strategy in a future update
        if (self::syncResultIndicatesFailure($syncResultDetails)) {
          $this->statusService->markFullInventoryFailed($manual);
        } else {
          $this->statusService->markFullInventoryComplete($manual);
        }
      } catch (\Exception $e) {
        $this->statusService->markFullInventoryFailed($manual, $e);
      }

      return $this->statusService->getServiceState();
    } finally {
      $externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic") . "use of full inventory service.");

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
   * Check if the state of the FullInventoryService has been "running" for more than the maximum alotted time
   * This functionality was extracted from the old UpdateFullInventoryStatusCron
   *
   * @return boolean
   */
  private function serviceHasBeenRunningTooLong(): bool
  {
    if ($this->statusService->isFullInventoryRunning()) {
      $lastStateChange = $this->statusService->getStateChangeTime();
      if (!$lastStateChange || (\time() - \strtotime($lastStateChange)) > self::MAX_FULL_INVENTORY_TIME) {
        $this->logger->warning(TranslationHelper::getLoggerKey(self::LOG_KEY_LONG_RUN), [
          'additionalInfo' => ['lastStateChange' => $lastStateChange, 'maximumTime' => self::MAX_FULL_INVENTORY_TIME],
          'method' => __METHOD__
        ]);
        return true;
      }
    }

    return false;
  }
}
