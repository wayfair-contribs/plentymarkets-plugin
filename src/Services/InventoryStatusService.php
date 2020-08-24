<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Service for setting / getting state of Inventory Services
 */
class InventoryStatusService
{
  const LOG_KEY_STATE_CHECK_FULL = 'fullInventoryStateCheck';
  const LOG_KEY_STATE_CHANGE_FULL = 'fullInventoryStateChange';
  const LOG_KEY_RESET_FULL = 'fullInventoryReset';

  const LOG_KEY_STATE_CHECK_PARTIAL = 'partialInventoryStateCheck';
  const LOG_KEY_STATE_CHANGE_PARTIAL = 'partialInventoryStateChange';
  const LOG_KEY_RESET_PARTIAL = 'partialInventoryReset';

  const STATUS = 'status';
  const STATE_CHANGE_TIMESTAMP = 'stateChangeTimestamp';
  const LAST_COMPLETION = 'lastCompletion';
  const LAST_ATTEMPT_TIMESTAMP = 'lastAttemptTimestamp';
  const LAST_ATTEMPT_SUCCEEDED = 'lastAttemptSucceeded';

  const STATE_RUNNING = 'running';
  const STATE_IDLE = 'idle';

  const INVENTORY_CRON_STATUS_FULL = 'full_inventory_cron_status';
  const INVENTORY_STATUS_UPDATED_AT_FULL = 'full_inventory_status_updated_at';
  const INVENTORY_LAST_COMPLETION_FULL = "full_inventory_last_completion";
  const INVENTORY_LAST_ATTEMPT_FULL = "full_inventory_last_attempt";
  const INVENTORY_SUCCESS_FULL = "full_inventory_success";

  const INVENTORY_CRON_STATUS_PARTIAL = 'partial_inventory_cron_status';
  const INVENTORY_STATUS_UPDATED_AT_PARTIAL = 'partial_inventory_status_updated_at';
  const INVENTORY_LAST_COMPLETION_PARTIAL = "partial_inventory_last_completion";
  const INVENTORY_LAST_ATTEMPT_PARTIAL = "partial_inventory_last_attempt";
  const INVENTORY_SUCCESS_PARTIAL = "partial_inventory_success";

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * InventoryStatusService constructor.
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
   * @param bool $full
   * @param string $state
   * @param string $timestamp
   * @return string|null
   */
  private function setServiceState($full, $state, $timestamp = null)
  {
    if (!isset($timestamp) || empty($timestamp)) {
      $timestamp = self::getCurrentTimestamp();
    }

    $statusKey = self::INVENTORY_CRON_STATUS_PARTIAL;
    $logKeyStateChange = self::LOG_KEY_STATE_CHANGE_PARTIAL;
    if ($full) {
      $statusKey = self::INVENTORY_CRON_STATUS_PARTIAL;
      $logKeyStateChange = self::LOG_KEY_STATE_CHANGE_FULL;
    }

    $oldState = $this->keyValueRepository->get($statusKey);

    if ($oldState != $state) {
      $this->keyValueRepository->putOrReplace($statusKey, $state);
      // this replaces flaky code in KeyValueRepository that was attempting to do change tracking.
      $ts = $this->markStateChange($statusKey, $timestamp);

      $this->logger->debug(TranslationHelper::getLoggerKey($logKeyStateChange), [
        'additionalInfo' => [
          'oldState' => $oldState,
          'newState' => $state
        ],
        'method' => __METHOD__
      ]);
    }

    return $oldState;
  }

  /**
   * Get the global state of the Full Inventory service,
   * as an array of details
   *
   * @param bool $full
   *
   * @return array
   */
  public function getServiceState(bool $full): array
  {
    return [
      self::STATUS => $this->getServiceStatusValue($full),
      self::STATE_CHANGE_TIMESTAMP => $this->getStateChangeTime($full),
      self::LAST_COMPLETION => $this->getLastCompletionTime($full),
      self::LAST_ATTEMPT_TIMESTAMP => $this->getLastAttemptTime($full),
      self::LAST_ATTEMPT_SUCCEEDED => $this->getLatestAttemptSuccess($full)
    ];
  }

  /**
   * Get service status value from storage
   *
   * @param bool $full
   *
   * @return string
   */
  private function getServiceStatusValue(bool $full): string
  {
    $keyStatus = self::INVENTORY_CRON_STATUS_PARTIAL;
    $logKeyStateCheck = self::LOG_KEY_STATE_CHANGE_PARTIAL;
    if ($full) {
      $keyStatus = self::INVENTORY_CRON_STATUS_FULL;
      $logKeyStateCheck = self::LOG_KEY_STATE_CHANGE_FULL;
    }

    $state = $this->keyValueRepository->get($keyStatus);

    $this->logger->debug(TranslationHelper::getLoggerKey($logKeyStateCheck), [
      'additionalInfo' => ['state' => $state],
      'method' => __METHOD__
    ]);

    return $state;
  }

  /**
   * Check if a Full Inventory sync is running
   *
   * @param $full
   *
   * @return boolean
   */
  public function isInventoryRunning(bool $full): bool
  {
    return self::STATE_RUNNING == $this->getServiceStatusValue($full);
  }

  /**
   * Get the global timestamp for the last change to Full Inventory
   *
   * @param bool $full
   *
   * @return string
   */
  public function getStateChangeTime(bool $full): string
  {
    $key = self::INVENTORY_STATUS_UPDATED_AT_PARTIAL;
    if ($full) {
      $key = self::INVENTORY_STATUS_UPDATED_AT_FULL;
    }
    return $this->keyValueRepository->get($key);
  }

  /**
   * Get the timestamp for last successful sync
   *
   * @param bool $full
   *
   * @return string
   */
  public function getLastCompletionTime(bool $full): string
  {
    $key = self::INVENTORY_LAST_COMPLETION_PARTIAL;
    if ($full) {
      $key = self::INVENTORY_LAST_COMPLETION_FULL;
    }

    return $this->keyValueRepository->get($key);
  }

  /**
   * Get the timestamp for an attempt to sync
   *
   * @param bool $full
   *
   * @return string|null
   */
  public function getLastAttemptTime(bool $full)
  {
    $key = self::INVENTORY_LAST_ATTEMPT_PARTIAL;
    if ($full) {
      $key = self::INVENTORY_CRON_STATUS_FULL;
    }

    return $this->keyValueRepository->get($key);
  }

  public function getLatestAttemptSuccess(bool $full): bool
  {
    $key = self::INVENTORY_SUCCESS_PARTIAL;
    if ($full) {
      $key = self::INVENTORY_SUCCESS_FULL;
    }

    $flag = $this->keyValueRepository->get($key);

    return isset($flag) && $flag;
  }

  /**
   * Set the global timestamp for an attempt to sync,
   * and return it.
   *
   * @param $full
   * @param $manual
   *
   * @return string
   */
  function markInventoryStarted(bool $full): string
  {
    $keyLastAttempt = self::INVENTORY_LAST_ATTEMPT_PARTIAL;

    $ts = self::getCurrentTimestamp();
    $this->keyValueRepository->putOrReplace($keyLastAttempt, $ts);
    // timestamp on state change should match last attempt
    $this->setServiceState($full, self::STATE_RUNNING, $ts);

    return $ts;
  }

  /**
   * Set the global timestamp for a successful sync to now, and update related fields
   * @param bool $full
   * @param bool $manual
   * @param \Exception $exception
   * @return void
   */
  function markInventoryFailed(bool $full)
  {
    $keySuccessFlag = self::INVENTORY_SUCCESS_PARTIAL;
    if ($full) {
      $keySuccessFlag = self::INVENTORY_SUCCESS_PARTIAL;
    }

    $this->keyValueRepository->putOrReplace($keySuccessFlag, false);
    $this->setServiceState($full, self::STATE_IDLE);
  }

  /**
   * Set the global Timestamp for a successful sync to now, and update related fields
   *
   * @param bool $full
   * @param bool $manual
   * @return void
   */
  function markInventoryComplete(bool $full)
  {
    $keyCompletion = self::INVENTORY_LAST_COMPLETION_PARTIAL;
    $keySuccessFlag = self::INVENTORY_SUCCESS_PARTIAL;
    if ($full) {
      $keyCompletion = self::INVENTORY_LAST_COMPLETION_FULL;
      $keySuccessFlag = self::INVENTORY_SUCCESS_PARTIAL;
    }

    $ts = self::getCurrentTimestamp();
    $this->keyValueRepository->putOrReplace($keyCompletion, $ts);
    $this->keyValueRepository->putOrReplace($keySuccessFlag, true);
    // timestamp on state change should match timestamp of last completion
    $this->setServiceState(self::STATE_IDLE, $ts);
  }

  /**
   * Set global timestamp for service interaction, and return the timestamp
   *
   * @param bool $full,
   * @param string $timestamp
   * @return string
   */
  private function markStateChange($full, $timestamp = null): string
  {
    if (!isset($timestamp) || empty($timestamp)) {
      $timestamp = self::getCurrentTimestamp();
    }

    $key = self::INVENTORY_STATUS_UPDATED_AT_PARTIAL;
    if ($full) {
      $key = self::INVENTORY_STATUS_UPDATED_AT_FULL;
    }

    $this->keyValueRepository->putOrReplace($key, $timestamp);

    return $timestamp;
  }

  /**
   * Get a timestamp for setting global values
   *
   * @return string
   */
  private static function getCurrentTimestamp(): string
  {
    return date('Y-m-d H:i:s.u P');
  }

  /**
   * Rollback the state
   *
   * @param $full
   * @param $manual
   * @return void
   */
  function resetState(bool $full, bool $manual = false)
  {
    $logKey = self::LOG_KEY_RESET_PARTIAL;
    if ($full) {
      $logKey = self::LOG_KEY_RESET_FULL;
    }

    $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
      'additionalInfo' => ['manual' => (string) $manual],
      'method' => __METHOD__
    ]);

    // rewind timestamp to when we last tried to sync
    $this->setServiceState($full, self::STATE_IDLE, $this->getLastAttemptTime($full));
  }
}
