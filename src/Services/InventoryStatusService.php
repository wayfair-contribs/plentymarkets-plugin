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
  const LOG_KEY_CLEAR_FULL = 'fullInventoryClear';

  const LOG_KEY_STATE_CHECK_PARTIAL = 'partialInventoryStateCheck';
  const LOG_KEY_STATE_CHANGE_PARTIAL = 'partialInventoryStateChange';
  const LOG_KEY_RESET_PARTIAL = 'partialInventoryReset';
  const LOG_KEY_CLEAR_PARTIAL = 'partialInventoryClear';

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
  const INVENTORY_LAST_SUCCESS_START_FULL = "full_inventory_last_success_start";

  const KEYS_FOR_FULL = [
    self::INVENTORY_CRON_STATUS_FULL,
    self::INVENTORY_STATUS_UPDATED_AT_FULL,
    self::INVENTORY_LAST_COMPLETION_FULL,
    self::INVENTORY_SUCCESS_FULL,
    self::INVENTORY_LAST_SUCCESS_START_FULL
  ];

  const INVENTORY_CRON_STATUS_PARTIAL = 'partial_inventory_cron_status';
  const INVENTORY_STATUS_UPDATED_AT_PARTIAL = 'partial_inventory_status_updated_at';
  const INVENTORY_LAST_COMPLETION_PARTIAL = "partial_inventory_last_completion";
  const INVENTORY_LAST_ATTEMPT_PARTIAL = "partial_inventory_last_attempt";
  const INVENTORY_SUCCESS_PARTIAL = "partial_inventory_success";
  const INVENTORY_LAST_SUCCESS_START_PARTIAL = "partial_inventory_last_success_start";

  const KEYS_FOR_PARTIAL = [
    self::INVENTORY_CRON_STATUS_PARTIAL,
    self::INVENTORY_STATUS_UPDATED_AT_PARTIAL,
    self::INVENTORY_LAST_COMPLETION_PARTIAL,
    self::INVENTORY_LAST_ATTEMPT_PARTIAL,
    self::INVENTORY_SUCCESS_PARTIAL,
    self::INVENTORY_LAST_SUCCESS_START_PARTIAL
  ];

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
   * Set the global status of Full Inventory syncing,
   * returning the old state.
   * @param bool $full
   * @param string $status
   * @param string $timestamp
   * @return string
   */
  private function setServiceStatusValue($full, $statusValue, $timestamp = null): string
  {
    if (!isset($timestamp) || empty($timestamp)) {
      $timestamp = self::getCurrentTimestamp();
    }

    $statusKey = $full ? self::INVENTORY_CRON_STATUS_FULL : self::INVENTORY_CRON_STATUS_PARTIAL;
    $logKeyStateChange = $full ? self::LOG_KEY_STATE_CHANGE_FULL : self::LOG_KEY_STATE_CHANGE_PARTIAL;

    $oldStatus = $this->getServiceStatusValue($full);

    if ($oldStatus != $statusValue) {
      $this->keyValueRepository->putOrReplace($statusKey, $statusValue);
      // this replaces flaky code in KeyValueRepository that was attempting to do change tracking.
      $ts = $this->markStateChange($statusKey, $timestamp);

      $this->logger->debug(TranslationHelper::getLoggerKey($logKeyStateChange), [
        'additionalInfo' => [
          'oldStatus' => $oldStatus,
          'newStatus' => $statusValue
        ],
        'method' => __METHOD__
      ]);
    }

    return $oldStatus;
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
    $keyStatus = $full ? self::INVENTORY_CRON_STATUS_FULL : self::INVENTORY_CRON_STATUS_PARTIAL;
    $logKeyStateCheck = $full ? self::LOG_KEY_STATE_CHECK_FULL : self::LOG_KEY_STATE_CHECK_PARTIAL;

    $state = $this->keyValueRepository->get($keyStatus);

    if (!isset($state))
    {
      $state = self::STATE_IDLE;
    }

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
    $key = $full ? self::INVENTORY_STATUS_UPDATED_AT_FULL : self::INVENTORY_STATUS_UPDATED_AT_PARTIAL;

    $ts = $this->keyValueRepository->get($key);

    if (isset($ts))
    {
      return $ts;
    }

    return '';
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
    $key = $full ? self::INVENTORY_LAST_COMPLETION_FULL : self::INVENTORY_LAST_COMPLETION_PARTIAL;

    $ts = $this->keyValueRepository->get($key);

    if (isset($ts))
    {
      return $ts;
    }

    return '';
  }

  /**
   * Get the timestamp for any attempt to sync
   *
   * @param bool $full
   *
   * @return string
   */
  public function getLastAttemptTime(bool $full): string
  {
    $key = $full ? self::INVENTORY_LAST_ATTEMPT_FULL : self::INVENTORY_LAST_ATTEMPT_PARTIAL;

    $ts = $this->keyValueRepository->get($key);

    if (isset($ts))
    {
      return $ts;
    }

    return '';
  }

  public function getLatestAttemptSuccess(bool $full): bool
  {
    $key = $full ? self::INVENTORY_SUCCESS_FULL : self::INVENTORY_SUCCESS_PARTIAL;

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
  public function markInventoryStarted(bool $full): string
  {
    $keyLastAttempt = self::INVENTORY_LAST_ATTEMPT_PARTIAL;

    $ts = self::getCurrentTimestamp();
    $this->keyValueRepository->putOrReplace($keyLastAttempt, $ts);
    // timestamp on state change should match last attempt
    $this->setServiceStatusValue($full, self::STATE_RUNNING, $ts);

    return $ts;
  }

  /**
   * Set the global timestamp for a successful sync to now, and update related fields
   * @param bool $full
   * @param bool $manual
   * @param \Exception $exception
   * @return void
   */
  public function markInventoryFailed(bool $full): void
  {
    $keySuccessFlag = $full ? self::INVENTORY_SUCCESS_FULL : self::INVENTORY_SUCCESS_PARTIAL;

    $this->keyValueRepository->putOrReplace($keySuccessFlag, false);
    $this->setServiceStatusValue($full, self::STATE_IDLE);
  }

  /**
   * Set the global Timestamp for a successful sync to now, and update related fields
   *
   * @param bool $full
   * @param bool $manual
   * @return void
   */
  public function markInventoryComplete(bool $full, string $startTime): void
  {
    $keyCompletion = $full ? self::INVENTORY_LAST_COMPLETION_FULL : self::INVENTORY_LAST_COMPLETION_PARTIAL;
    $keySuccessFlag = $full ? self::INVENTORY_SUCCESS_FULL : self::INVENTORY_SUCCESS_PARTIAL;
    $keyStartTime = $full ? self::INVENTORY_LAST_SUCCESS_START_FULL : self::INVENTORY_LAST_SUCCESS_START_PARTIAL;

    $ts = self::getCurrentTimestamp();
    $this->keyValueRepository->putOrReplace($keyCompletion, $ts);
    $this->keyValueRepository->putOrReplace($keySuccessFlag, true);
    $this->keyValueRepository->putOrReplace($keyStartTime, $startTime);
    // timestamp on state change should match timestamp of last completion
    $this->setServiceStatusValue(self::STATE_IDLE, $ts);
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

    $key = $full ? self::INVENTORY_STATUS_UPDATED_AT_FULL : self::INVENTORY_STATUS_UPDATED_AT_PARTIAL;

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
  public function resetState(bool $full, bool $manual = false): void
  {
    $logKey = $full ? self::LOG_KEY_RESET_FULL : self::LOG_KEY_RESET_PARTIAL;

    $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
      'additionalInfo' => ['manual' => (string) $manual],
      'method' => __METHOD__
    ]);

    // rewind timestamp to when we last tried to sync
    $this->setServiceStatusValue($full, self::STATE_IDLE, $this->getLastAttemptTime($full));
  }

  /**
   * Clear all data for a type of sync
   *
   * @param boolean $full
   * @param boolean $manual
   * @return void
   */
  public function clearState(bool $full, bool $manual = false): void
  {
    $keys = $full ? self::KEYS_FOR_FULL : self::KEYS_FOR_PARTIAL;

    foreach ($keys as $key) {
      // using 'putOrReplace' with null is a safe delete operation.
      $this->keyValueRepository->putOrReplace($key, null);
    }

    $logKey = $full ? self::LOG_KEY_CLEAR_FULL : self::LOG_KEY_CLEAR_PARTIAL;

    $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
      'additionalInfo' => ['manual' => (string) $manual],
      'method' => __METHOD__
    ]);
  }

  /**
   * Get the timestamp for the time the last successful sync started
   *
   * @param bool $full
   *
   * @return string
   */
  public function getLastSuccessfulAttemptTime(bool $full): string
  {
    $key = $full ? self::INVENTORY_LAST_SUCCESS_START_FULL : self::INVENTORY_LAST_SUCCESS_START_PARTIAL;

    $ts = $this->keyValueRepository->get($key);

    if (isset($ts))
    {
      return $ts;
    }

    return '';
  }
}
