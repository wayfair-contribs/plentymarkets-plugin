<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Service for setting / getting state of Inventory Services
 */
class InventoryStatusService
{
  const OVERDUE_TIME_FULL = 90000;
  const OVERDUE_TIME_PARTIAL = 1800;

  const LOG_KEY_STATE_CHANGE = 'inventoryStateChange';
  const LOG_KEY_STATE_CHECK = 'inventoryStateCheck';
  const LOG_KEY_STATE_CLEAR = 'inventoryStateClear';
  const LOG_KEY_INVENTORY_VERSION_CHANGE = 'inventoryVersionChange';

  const RESPONSE_KEY_STATUS = 'status';
  const RESPONSE_KEY_DETAILS = 'details';
  const RESPONSE_DETAILS_KEY_LAST_COMPLETION_START = 'completedStart';
  const RESPONSE_DETAILS_KEY_LAST_COMPLETION_END = 'completedEnd';
  const RESPONSE_DETAILS_COMPLETED_AMOUNT = 'completedAmount';
  const RESPONSE_DETAILS_KEY_LAST_ATTEMPT_TIMESTAMP = 'attemptedStart';
  const RESPONSE_DETAILS_KEY_OVERDUE = 'overdue';

  const STATE_IDLE = 'idle';
  const FULL = 'full';
  const PARTIAL = 'partial';

  const DB_KEY_INVENTORY_DATA_VERSION = 'inventory_data_version';

  const DB_KEY_INVENTORY_STATUS = 'inventory_status';

  const DB_KEY_INVENTORY_LAST_COMPLETION_END_FULL = "full_inventory_last_completion_end";
  const DB_KEY_INVENTORY_LAST_ATTEMPT_FULL = "full_inventory_last_attempt";
  const DB_KEY_INVENTORY_LAST_COMPLETION_START_FULL = "full_inventory_last_completion_start";
  const DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_FULL = "full_inventory_last_completion_amount";

  const DB_KEY_INVENTORY_LAST_COMPLETION_END_PARTIAL = "partial_inventory_last_completion";
  const DB_KEY_INVENTORY_LAST_ATTEMPT_PARTIAL = "partial_inventory_last_attempt";
  const DB_KEY_INVENTORY_LAST_COMPLETION_START_PARTIAL = "partial_inventory_last_completion_start";
  const DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_PARTIAL = "partial_inventory_last_completion_amount";

  const DB_KEYS = [
    self::DB_KEY_INVENTORY_DATA_VERSION,
    self::DB_KEY_INVENTORY_STATUS,
    self::DB_KEY_INVENTORY_LAST_COMPLETION_END_FULL,
    self::DB_KEY_INVENTORY_LAST_ATTEMPT_FULL,
    self::DB_KEY_INVENTORY_LAST_COMPLETION_START_FULL,
    self::DB_KEY_INVENTORY_LAST_COMPLETION_END_PARTIAL,
    self::DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_FULL,
    self::DB_KEY_INVENTORY_LAST_ATTEMPT_PARTIAL,
    self::DB_KEY_INVENTORY_LAST_COMPLETION_START_PARTIAL,
    self::DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_PARTIAL
  ];
  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var LoggerContract
   */
  private $logger;

  /** @var AbstractConfigHelper */
  private $configHelper;

  /**
   * InventoryStatusService constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   * @param LoggerContract $logger
   */
  public function __construct(
    KeyValueRepository $keyValueRepository,
    LoggerContract $logger,
    AbstractConfigHelper $configHelper
  ) {
    $this->keyValueRepository = $keyValueRepository;
    $this->logger = $logger;
    $this->configHelper = $configHelper;

    $this->initStorage();
  }

  /**
   * Set the global status for full or partial inventory syncing,

   * returning the old state.
   * @param bool $full
   * @param string $statusValue
   * @param string $timestamp
   * @return string
   */
  private function setServiceStatusValue($statusValue): string
  {
    $oldStatus = $this->getServiceStatusValue();

    if ($oldStatus != $statusValue) {
      $this->keyValueRepository->putOrReplace(self::DB_KEY_INVENTORY_STATUS, $statusValue);

      $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_STATE_CHANGE), [
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
  public function getServiceState(): array
  {
    $stateArray = [
      self::RESPONSE_KEY_STATUS => $this->getServiceStatusValue()
    ];

    $detailsLoopInput = [self::FULL => true, self::PARTIAL => false];
    foreach ($detailsLoopInput as $key => $value) {
      $stateArray[self::RESPONSE_KEY_DETAILS][$key][self::RESPONSE_DETAILS_KEY_LAST_COMPLETION_START] = $this->getLastCompletionStart($value);
      $stateArray[self::RESPONSE_KEY_DETAILS][$key][self::RESPONSE_DETAILS_KEY_LAST_COMPLETION_END] = $this->getLastCompletionEnd($value);
      $stateArray[self::RESPONSE_KEY_DETAILS][$key][self::RESPONSE_DETAILS_COMPLETED_AMOUNT] = $this->getCompleteSyncSize($value);
      $stateArray[self::RESPONSE_KEY_DETAILS][$key][self::RESPONSE_DETAILS_KEY_LAST_ATTEMPT_TIMESTAMP] = $this->getLastAttemptStart($value);
      $stateArray[self::RESPONSE_KEY_DETAILS][$key][self::RESPONSE_DETAILS_KEY_OVERDUE] = $this->isOverdue($value);
    }

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_STATE_CHECK), [
      'additionalInfo' => ['stateArray' => $stateArray],
      'method' => __METHOD__
    ]);

    return $stateArray;
  }

  /**
   * Get service status value from storage
   *
   * @return string
   */
  private function getServiceStatusValue(): string
  {
    $state = $this->keyValueRepository->get(self::DB_KEY_INVENTORY_STATUS);

    if (!isset($state)) {
      $state = self::STATE_IDLE;
    }

    return $state;
  }

  /**
   * Check if an Inventory sync is running
   *
   * @return boolean
   */
  public function isInventoryRunning(): bool
  {
    return self::STATE_IDLE !== $this->getServiceStatusValue();
  }

  /**
   * Get the timestamp for last successful sync
   *
   * @param bool $full
   *
   * @return string
   */
  public function getLastCompletionEnd(bool $full): string
  {
    $key = $full ? self::DB_KEY_INVENTORY_LAST_COMPLETION_END_FULL : self::DB_KEY_INVENTORY_LAST_COMPLETION_END_PARTIAL;

    $ts = $this->keyValueRepository->get($key);

    if (isset($ts)) {
      return $ts;
    }

    return '';
  }

  /**
   * Get the recorded timestamp for starting a particular type
   *
   * @param bool $full
   *
   * @return string
   */
  public function getLastAttemptStart(bool $full): string
  {
    $key = $full ? self::DB_KEY_INVENTORY_LAST_ATTEMPT_FULL : self::DB_KEY_INVENTORY_LAST_ATTEMPT_PARTIAL;

    $ts = $this->keyValueRepository->get($key);

    if (isset($ts)) {
      return $ts;
    }

    return '';
  }

  /**
   * Get the recorded timestamp for the start of the last success
   *
   * @param bool $full
   *
   * @return string
   */
  public function getLastCompletionStart(bool $full): string
  {
    $key = $full ? self::DB_KEY_INVENTORY_LAST_COMPLETION_START_FULL : self::DB_KEY_INVENTORY_LAST_COMPLETION_START_PARTIAL;

    $ts = $this->keyValueRepository->get($key);

    if (isset($ts)) {
      return $ts;
    }

    return '';
  }

  /**
   * Set the global timestamp for an attempt to sync,
   * and return it.
   *
   * @param $full
   *
   * @return string
   */
  public function markInventoryStarted(bool $full): string
  {
    $ts = self::getCurrentTimestamp();

    $statusValue = $full ? self::FULL : self::PARTIAL;
    $this->setServiceStatusValue($statusValue);

    $keyForLastAttempt = $full ? self::DB_KEY_INVENTORY_LAST_ATTEMPT_FULL : self::DB_KEY_INVENTORY_LAST_ATTEMPT_PARTIAL;
    $this->keyValueRepository->putOrReplace($keyForLastAttempt, $ts);

    return $ts;
  }

  /**
   * Set the status back to idle state
   * @param bool $full
   * @return void
   */
  public function markInventoryIdle(): void
  {
    $this->setServiceStatusValue(self::STATE_IDLE);
  }

  /**
   * Set the global Timestamp for a successful sync to now, and update related fields
   *
   * @param bool $full
   * @param string $startTime
   * @return void
   */
  public function markInventoryComplete(bool $full, string $startTime, int $amount): void
  {

    $keyCompletionStart = $full ? self::DB_KEY_INVENTORY_LAST_COMPLETION_START_FULL : self::DB_KEY_INVENTORY_LAST_COMPLETION_START_PARTIAL;
    $keyCompletionEnd = $full ? self::DB_KEY_INVENTORY_LAST_COMPLETION_END_FULL : self::DB_KEY_INVENTORY_LAST_COMPLETION_END_PARTIAL;
    $keyCompletionAmount = $full ? self::DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_FULL : self::DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_PARTIAL;

    $ts = self::getCurrentTimestamp();
    $this->keyValueRepository->putOrReplace($keyCompletionEnd, $ts);
    $this->keyValueRepository->putOrReplace($keyCompletionStart, $startTime);
    $this->keyValueRepository->putOrReplace($keyCompletionAmount, $amount);
    // timestamp on state change should match timestamp of last completion
    $this->setServiceStatusValue(self::STATE_IDLE);
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
   * Clear all data for any type of sync
   *
   * @param boolean $manual
   * @return void
   */
  public function clearState(bool $manual = false): void
  {
    foreach (self::DB_KEYS as $key) {
      // using 'putOrReplace' with null is a safe delete operation.
      $this->keyValueRepository->putOrReplace($key, null);
    }

    $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_STATE_CLEAR), [
      'additionalInfo' => ['manual' => (string) $manual],
      'method' => __METHOD__
    ]);
  }

  /**
   * Get the timestamp of the sync that was started most recently
   *
   * @return string
   */
  public function getStartOfMostRecentAttempt(): string
  {
    $mostRecentPartial = $this->keyValueRepository->get(self::DB_KEY_INVENTORY_LAST_ATTEMPT_PARTIAL);
    $mostRecentFull = $this->keyValueRepository->get(self::DB_KEY_INVENTORY_LAST_ATTEMPT_FULL);

    if (!isset($mostRecentPartial) || empty($mostRecentPartial)) {
      return $mostRecentFull;
    }

    if (!isset($mostRecentFull) || empty($mostRecentFull)) {
      return $mostRecentPartial;
    }

    $numericPartial = strtotime($mostRecentPartial);
    if ($numericPartial <= 0) {
      return $mostRecentFull;
    }

    $numericFull = strtotime($mostRecentFull);
    if ($numericFull <= $numericPartial) {
      return $mostRecentPartial;
    }

    return $mostRecentFull;
  }

  /**
   * Get the amount of products that were synced during the last successful sync
   *
   * @param boolean $full full inventory?
   * @return integer
   */
  public function getCompleteSyncSize(bool $full): int
  {

    $keyCompletionAmount = $full ? self::DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_FULL : self::DB_KEY_INVENTORY_LAST_COMPLETION_AMOUNT_PARTIAL;

    $amt = $this->keyValueRepository->get($keyCompletionAmount);
    if (isset($amt)) {
      return $amt;
    }

    return 0;
  }

  /**
   * Get the amount of time (in seconds) that has elapsed since a good inventory sync
   *
   * @param boolean $full full inventory?
   * @return integer
   */
  public function timeSinceLastGoodSyncStart(bool $full): int
  {
    $lastGoodSyncStart = $this->getLastCompletionStart($full);

    if (isset($lastGoodSyncStart) && !empty($lastGoodSyncStart)) {
      $numericTime = strtotime($lastGoodSyncStart);
      if ($numericTime > 0) {
        return time() - $numericTime;
      }
    }

    return -1;
  }

  /**
   * Check if a type of sync needs to be checked on by a person or system
   *
   * @param boolean $full full inventory?
   * @return boolean
   */
  public function isOverdue(bool $full): bool
  {
    $timeSinceLastGoodFullStart = $this->timeSinceLastGoodSyncStart(true);
    if ($full) {
      return $timeSinceLastGoodFullStart < 0 || $timeSinceLastGoodFullStart > self::OVERDUE_TIME_FULL;
    }

    if ($timeSinceLastGoodFullStart < self::OVERDUE_TIME_PARTIAL)
    {
      // partial sync doesn't need to happen if a full sync did not happen yet,
      // or if a full sync happened recently.
      return false;
    }

    $timeSinceLastGoodPartialStart = $this->timeSinceLastGoodSyncStart(false);

    return $timeSinceLastGoodPartialStart < 0 || $timeSinceLastGoodPartialStart > self::OVERDUE_TIME_PARTIAL;
  }

  /**
   * Set up the inventory storage for use,
   * in case it does not exist or it is from an older version.
   *
   * @return boolean
   */
  private function initStorage(): bool
  {
    $storedVersion = $this->keyValueRepository->get(self::DB_KEY_INVENTORY_DATA_VERSION);
    $pluginVersion = $this->configHelper->getPluginVersion();

    if (!isset($storedVersion) || empty($storedVersion) || $storedVersion != $pluginVersion) {


      $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_VERSION_CHANGE), [
        'additionalInfo' => [
          'oldVersion' => json_encode($storedVersion),
          'newVersion' => json_encode($pluginVersion)
        ],
        'method' => __METHOD__
      ]);

      $this->clearState();
      $this->keyValueRepository->putOrReplace(self::DB_KEY_INVENTORY_DATA_VERSION, $pluginVersion);

      return true;
    }

    return false;
  }
}
