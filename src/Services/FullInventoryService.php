<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Repositories\KeyValueRepository;

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
  const LOG_KEY_START = 'fullInventoryStart';
  const LOG_KEY_END = 'fullInventoryEnd';
  const LOG_KEY_STATE_CHECK = "fullInventoryStateCheck";
  const LOG_KEY_LONG_RUN = "fullInventoryLongRunning";
  const LOG_KEY_FAILED = 'fullInventoryFailed';


  const STATUS = 'status';
  const STATE_CHANGE_TIMESTAMP = 'stateChangeTimestamp';
  const LAST_COMPLETION = 'lastCompletion';
  const LAST_ATTEMPT = 'lastAttempt';
  const LAST_ATTEMPT_SUCCEEDED = 'lastAttemptSucceeded';

  // TODO: make this user-configurable in a future update
  const MAX_FULL_INVENTORY_TIME = 7200;

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var InventoryUpdateService
   */
  private $inventoryUpdateService;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * FullInventoryService constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository, InventoryUpdateService $inventoryUpdateService,  LoggerContract $logger)
  {
    $this->keyValueRepository = $keyValueRepository;
    $this->inventoryUpdateService = $inventoryUpdateService;
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
      if ($this->isFullInventoryRunning() && !$this->serviceHasBeenRunningTooLong()) {
        $lastStartTime = $this->getLastAttemptTime();
        $stateArray = $this->getServiceState();
        $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_SKIPPED), [
          'additionalInfo' => ['manual' => (string) $manual, 'startedAt' => $lastStartTime, 'state' => $stateArray],
          'method' => __METHOD__
        ]);
        $externalLogs->addErrorLog(($manual ? "Manual " : "Automatic") . "Full inventory sync BLOCKED - full inventory sync is currently running");

        // early exit
        return $stateArray;
      }

      try {
        $this->markFullInventoryStarted($manual);

        $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");

        $syncResultDetails = $this->inventoryUpdateService->sync(true);

        // potential race conditions - change service management strategy in a future update
        if (self::syncResultIndicatesFailure($syncResultDetails)) {
          $this->markFullInventoryFailed($manual);
        } else {
          $this->markFullInventoryComplete($manual);
        }
      } catch (\Exception $e) {
        $this->markFullInventoryFailed($manual, $e);
      }

      return $this->getServiceState();
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
   * Get the global state of the Full Inventory service,
   * as an array of details
   *
   * @return array
   */
  public function getServiceState(): array
  {
    return [
      self::STATUS => $this->getServiceStatusValue(),
      self::STATE_CHANGE_TIMESTAMP => $this->getStateChangeTime(),
      self::LAST_COMPLETION => $this->getLastCompletionTime(),
      self::LAST_ATTEMPT => $this->getLastAttemptTime(),
      self::LAST_ATTEMPT_SUCCEEDED => $this->getLatestAttemptSuccess()
    ];
  }

  /**
   * Wrapper for getting FULL_INVENTORY_CRON_STATUS from storage
   *
   * @return string
   */
  private function getServiceStatusValue(): string
  {
    $state = $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_CRON_STATUS);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_STATE_CHECK), [
      'additionalInfo' => ['state' => $state],
      'method' => __METHOD__
    ]);

    return $state;
  }

  /**
   * Check if a Full Inventory sync is running
   *
   * @return boolean
   */
  public function isFullInventoryRunning(): bool
  {
    return AbstractConfigHelper::FULL_INVENTORY_CRON_RUNNING == $this->getServiceStatusValue();
  }

  /**
   * Get the global timestamp for the last change to Full Inventory
   *
   * @return string
   */
  public function getStateChangeTime(): string
  {
    return $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_STATUS_UPDATED_AT);
  }

  /**
   * Set the global state of Full Inventory syncing,
   * returning the old state.
   *
   * @param string $state
   * @return string
   */
  private function setServiceState($state): string
  {
    $oldState = $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_CRON_STATUS);
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_CRON_STATUS, $state);
    // this replaces flaky code in KeyValueRepository that was attempting to do change tracking.
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_STATUS_UPDATED_AT, self::getCurrentTimeStamp());

    return $oldState;
  }

  /**
   * Check if the state of the FullInventoryService has been "running" for more than the maximum alotted time
   * This functionality was extracted from the old UpdateFullInventoryStatusCron
   *
   * @return boolean
   */
  private function serviceHasBeenRunningTooLong(): bool
  {
    if ($this->isFullInventoryRunning()) {
      $lastStateChange = $this->getStateChangeTime();
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

  /**
   * Set the global timestamp for an attempt to sync to now, and update related fields
   *
   * @return void
   */
  private function markFullInventoryStarted(bool $manual = false)
  {
    $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_START), [
      'additionalInfo' => ['manual' => (string) $manual],
      'method' => __METHOD__
    ]);

    $ts = $this->markStateChange();
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_LAST_ATTEMPT, $ts);
    $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_RUNNING);
  }

  /**
   * Set the global timestamp for a successful sync to now, and update related fields
   *
   * @return void
   */
  private function markFullInventoryFailed(bool $manual = false, \Exception $exception = null)
  {
    $info = ['manual' => (string) $manual];
    if (isset($exception)) {
      $info['errorMessage'] = $exception->getMessage();
    }

    $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_FAILED), [
      'additionalInfo' => $info,
      'method' => __METHOD__
    ]);

    $ts = $this->markStateChange();
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_STATUS_UPDATED_AT, $ts);
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_SUCCESS, 'false');
    $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_IDLE);
  }

  /**
   * Set the global timestamp for a successful sync to now, and update related fields
   *
   * @return void
   */
  private function markFullInventoryComplete(bool $manual = false)
  {
    $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_END), [
      'manual' => (string) $manual, 'method' => __METHOD__
    ]);

    $ts = $this->markStateChange();
    $this->markStateChange($ts);
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_LAST_COMPLETION, $ts);
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_SUCCESS, 'true');
    $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_IDLE);
  }

  /**
   * Set global timestamp for service interaction, and return the timestamp
   *
   * @return string
   */
  private function markStateChange(): string
  {
    $ts = self::getCurrentTimeStamp();
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_STATUS_UPDATED_AT, $ts);

    return $ts;
  }

  /**
   * Get the global timestamp for a successful sync
   *
   * @return void
   */
  public function getLastCompletionTime()
  {
    return $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_LAST_COMPLETION);
  }

  /**
   * Get the global timestamp for an attempt to sync
   *
   * @return void
   */
  public function getLastAttemptTime()
  {
    return $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_LAST_ATTEMPT);
  }

  /**
   * Get a timestamp for setting global values
   *
   * @return string
   */
  private static function getCurrentTimeStamp(): string
  {
    return date('Y-m-d H:i:s.u P');
  }

  public function getLatestAttemptSuccess(): bool
  {
    $flag = $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_SUCCESS);
    if (!isset($flag)) {
      return false;
    }

    return $flag;
  }
}
