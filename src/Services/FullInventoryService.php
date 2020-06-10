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
 * Service module for performing a Full Inventory sync
 * 
 * The service may be started in the following ways:
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

    $syncResultDetails = [];
    $stateArray = [];

    try {
      $alreadyRunning = $this->isFullInventoryRunning();
      // FIXME: potential race conditions - change service management strategy in a future update
      // (but this is better than letting the old UpdateFullInventoryStatusCron randomly change service states)
      $lastRunTakingToolong = $this->serviceHasBeenRunningTooLong();
      $lastStateChange = $this->getStateChangeTime();

      if ($alreadyRunning && !$lastRunTakingToolong) {
        $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_SKIPPED), [
          'additionalInfo' => ['manual' => (string) $manual, 'startedAt' => $lastStateChange],
          'method' => __METHOD__
        ]);

        $externalLogs->addErrorLog(($manual ? "Manual " : "Automatic") . "Full inventory sync BLOCKED - full inventory sync is currently running");
      } else {
        try {
          $lastState = $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_RUNNING);

          $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");
          $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_START), [
            'additionalInfo' => ['manual' => (string) $manual, 'lastState' => $lastState, 'lastStateChange' => $lastStateChange],
            'method' => __METHOD__
          ]);

          $syncResultDetails = $this->inventoryUpdateService->sync(true);
          // TODO: replace string literal with constant
          $numFailures = $syncResultDetails['inventorySaveFail'];
          $syncError = $syncResultDetails['errorMessage'];

          // FIXME: potential race conditions - change service management strategy in a future update
          if ((isset($syncError) && !empty($syncError)) || (isset($numFailures) && $numFailures > 0 ))
          {
            // TODO: log about failures
            $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_FAILED);
          }
          else
          {
            $this->markFullInventoryComplete();
            $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_IDLE);
          }
        } catch (\Exception $e) {
          $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_FAILED);
          throw $e;
        }
      }

      // capture state to put it into logs (see finally block)
      $stateArray = $this->getServiceState();
      // provide current state to clients
      return $stateArray;
    } catch (\Exception $e) {
      // FIXME: this needs its own log key
      $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_ERROR), [
        'additionalInfo' => ['manual' => (string) $manual, 'message' => $e->getMessage()],
        'method' => __METHOD__
      ]);
    } finally {
      $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_END), [
        'additionalInfo' => ['state' => $stateArray, 'details' => $syncResultDetails],
        'method' => __METHOD__
      ]);

      $externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");
      /** @var LogSenderService $logSenderService */
      $logSenderService = pluginApp(LogSenderService::class);
      $logSenderService->execute($externalLogs->getLogs());
    }
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
      'status' => $this->getServiceStatusValue(),
      'stateChangeTimestamp' => $this->getStateChangeTime(),
      'lastCompletion' => $this->getLastCompletionTime()
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
   * Set the global timestamp for a successful sync to now
   *
   * @return void
   */
  private function markFullInventoryComplete()
  {
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_LAST_COMPLETION, self::getCurrentTimeStamp());
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
   * Get a timestamp for setting global values
   *
   * @return void
   */
  private static function getCurrentTimeStamp()
  {
    return date('Y-m-d H:i:s.u P');
  }
}
