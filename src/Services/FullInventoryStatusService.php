<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Service for setting / getting state of Full Inventory Service
 */
class FullInventoryStatusService
{
  const LOG_KEY_STATE_CHECK = 'fullInventoryStateCheck';
  const LOG_KEY_START = 'fullInventoryStart';
  const LOG_KEY_END = 'fullInventoryEnd';
  const LOG_KEY_FAILED = 'fullInventoryFailed';
  const LOG_KEY_RESET = 'fullInventoryReset';
  const LOG_KEY_STATE_CHANGE = 'fullInventoryStateChange';

  const STATUS = 'status';
  const STATE_CHANGE_TIMESTAMP = 'stateChangeTimestamp';
  const LAST_COMPLETION = 'lastCompletion';
  const LAST_ATTEMPT_TIMESTAMP = 'lastAttemptTimestamp';
  const LAST_ATTEMPT_SUCCEEDED = 'lastAttemptSucceeded';

  const FULL_INVENTORY_CRON_STATUS = 'full_inventory_cron_status';
  const FULL_INVENTORY_STATUS_UPDATED_AT = 'full_inventory_status_updated_at';
  const FULL_INVENTORY_CRON_RUNNING = 'running';
  const FULL_INVENTORY_CRON_IDLE = 'idle';
  const FULL_INVENTORY_LAST_COMPLETION = "full_inventory_last_completion";
  const FULL_INVENTORY_LAST_ATTEMPT = "full_inventory_last_attempt";
  const FULL_INVENTORY_SUCCESS = "full_inventory_success";

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * FullInventoryStatusService constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   * @param LoggerContract $logger
   */
  public function __construct(
    KeyValueRepository $keyValueRepository,
    LoggerContract $logger
  ) {
    $this->keyValueRepository = $keyValueRepository;
    $this->logger = $logger;
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
    $oldState = $this->keyValueRepository->get(self::FULL_INVENTORY_CRON_STATUS);
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_CRON_STATUS, $state);
    // this replaces flaky code in KeyValueRepository that was attempting to do change tracking.
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_STATUS_UPDATED_AT, self::getCurrentTimeStamp());


    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_STATE_CHANGE), [
      'additionalInfo' => [
        'oldState' => $oldState,
        'newState' => $state
      ],
      'method' => __METHOD__
    ]);

    return $oldState;
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
      self::LAST_ATTEMPT_TIMESTAMP => $this->getLastAttemptTime(),
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
    $state = $this->keyValueRepository->get(self::FULL_INVENTORY_CRON_STATUS);

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
    return self::FULL_INVENTORY_CRON_RUNNING == $this->getServiceStatusValue();
  }

  /**
   * Get the global timestamp for the last change to Full Inventory
   *
   * @return string
   */
  public function getStateChangeTime(): string
  {
    return $this->keyValueRepository->get(self::FULL_INVENTORY_STATUS_UPDATED_AT);
  }

  /**
   * Get the global timestamp for a successful sync
   *
   * @return void
   */
  public function getLastCompletionTime()
  {
    return $this->keyValueRepository->get(self::FULL_INVENTORY_LAST_COMPLETION);
  }

  /**
   * Get the global timestamp for an attempt to sync
   *
   * @return void
   */
  public function getLastAttemptTime()
  {
    return $this->keyValueRepository->get(self::FULL_INVENTORY_LAST_ATTEMPT);
  }

  public function getLatestAttemptSuccess(): bool
  {
    $flag = $this->keyValueRepository->get(self::FULL_INVENTORY_SUCCESS);

    return isset($flag) && $flag;
  }

  /**
   * Set the global timestamp for an attempt to sync to now, and update related fields
   *
   * @return void
   */
  function markFullInventoryStarted(bool $manual = false)
  {
    $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_START), [
      'additionalInfo' => ['manual' => (string) $manual],
      'method' => __METHOD__
    ]);

    $ts = $this->markStateChange();
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_LAST_ATTEMPT, $ts);
    $this->setServiceState(self::FULL_INVENTORY_CRON_RUNNING);
  }

  /**
   * Set the global timestamp for a successful sync to now, and update related fields
   *
   * @return void
   */
  function markFullInventoryFailed(bool $manual = false, \Exception $exception = null)
  {
    $info = ['manual' => (string) $manual];
    if (isset($exception)) {
      $info['errorMessage'] = $exception->getMessage();
    }

    $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_FAILED), [
      'additionalInfo' => $info,
      'method' => __METHOD__
    ]);

    $ts = $this->markStateChange();
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_STATUS_UPDATED_AT, $ts);
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_SUCCESS, false);
    $this->setServiceState(self::FULL_INVENTORY_CRON_IDLE);
  }

  /**
   * Set the global timestamp for a successful sync to now, and update related fields
   *
   * @return void
   */
  function markFullInventoryComplete(bool $manual = false)
  {
    $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_END), [
      'manual' => (string) $manual, 'method' => __METHOD__
    ]);

    $ts = $this->markStateChange();
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_LAST_COMPLETION, $ts);
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_SUCCESS, true);
    $this->setServiceState(self::FULL_INVENTORY_CRON_IDLE);
  }

  /**
   * Set global timestamp for service interaction, and return the timestamp
   *
   * @return string
   */
  private function markStateChange(): string
  {
    $ts = self::getCurrentTimeStamp();
    $this->keyValueRepository->putOrReplace(self::FULL_INVENTORY_STATUS_UPDATED_AT, $ts);

    return $ts;
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

  /**
   * Clear status in case the plugin container was reset while service was running
   *
   * @return void
   */
  function markFullInventoryIdle(bool $manual = false)
  {
    $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_RESET), [
      'additionalInfo' => ['manual' => (string) $manual],
      'method' => __METHOD__
    ]);

    // don't mark this as a state change - use the last one
    $this->setServiceState(self::FULL_INVENTORY_CRON_IDLE);
  }
}
