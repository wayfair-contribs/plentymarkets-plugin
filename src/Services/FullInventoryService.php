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
      }
      else
      {
        $lastState = $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_RUNNING);

        $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");
        $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_START), [
          'additionalInfo' => ['manual' => (string) $manual, 'lastState' => $lastState, 'lastStateChange' => $lastStateChange],
          'method' => __METHOD__
        ]);

        $result = null;
        try {
         
          $result = $this->inventoryUpdateService->sync(true);

          // FIXME: potential race conditions - change service management strategy in a future update
          $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_IDLE);
        } catch (\Exception $e) {
          
          $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_ERROR), [
            'additionalInfo' => ['manual' => (string) $manual, 'message' => $e->getMessage()],
            'method' => __METHOD__
          ]);

          $this->setServiceState(AbstractConfigHelper::FULL_INVENTORY_CRON_FAILED);
        } finally {

          $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_END), [
            'additionalInfo' => ['result' => $result],
            'method' => __METHOD__
          ]);

          $externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic") . "full inventory sync.");
        }
      }

      return ['status' => $this->getServiceState(), 'stateChangeTimestamp' => $this->getStateChangeTime(), 'timestamp' => self::getCurrentTimeStamp()];
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }

  private function getServiceState(): string
  {
    $state = $this->keyValueRepository->get(AbstractConfigHelper::FULL_INVENTORY_CRON_STATUS);
    
    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_STATE_CHECK), [
      'additionalInfo' => ['state' => $state],
      'method' => __METHOD__
    ]);

    return $state;
  }

  public function isFullInventoryRunning(): bool
  {
    return AbstractConfigHelper::FULL_INVENTORY_CRON_RUNNING == $this->getServiceState();
  }

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
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_STATUS_UPDATED_AT, self::getCurrentTime());

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
    if ($this->isFullInventoryRunning())
    {
      $lastStateChange = $this->getStateChangeTime();
      if(!$lastStateChange || (\time() - \strtotime($lastStateChange)) > self::MAX_FULL_INVENTORY_TIME)
      {
        $this->logger->warning(TranslationHelper::getLoggerKey(self::LOG_KEY_LONG_RUN), [
          'additionalInfo' => ['lastStateChange' => $lastStateChange, 'maximumTime' => self::MAX_FULL_INVENTORY_TIME],
          'method' => __METHOD__
          ]);
        return true;
      }
    }
    
    return false;
  }

  private static function getCurrentTimeStamp()
  {
    return date('Y-m-d H:i:s.u P');
  }
}
