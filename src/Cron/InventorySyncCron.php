<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Exception;
use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\FullInventorySyncInProgressException;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryUpdateService;

class InventorySyncCron extends Cron
{
  const LOG_KEY_INVENTORY_ERRORS = 'inventoryErrors';

  const SECONDS_BETWEEN_TRIES = 300;

  /** @var bool */
  private $fullInventory;

  /** @var InventoryUpdateService */
  private $inventoryUpdateService;

  /** @var LoggerContract */
  private $loggerContract;

  /**
   * InventorySyncCron constructor.
   *
   */
  public function __construct(
    bool $fullInventory = false,
    InventoryUpdateservice $inventoryUpdateService,
    LoggerContract $loggerContract
  ) {
    $this->fullInventory = $fullInventory;
    $this->inventoryUpdateService = $inventoryUpdateService;
    $this->loggerContract = $loggerContract;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronStartedMessage'), [
      'additionalInfo' => [
        'full' => $this->fullInventory
      ],
      'method' => __METHOD__
    ]);
    $syncResult = null;
    $maxAttempts = $this->fullInventory ? 3 : 1;
    $attempts = 0;

    try {

      while ($attempts++ <= $maxAttempts) {
        try {
          $syncResult = $this->inventoryUpdateService->sync($this->fullInventory);
        } catch (FullInventorySyncInProgressException $e) {
          $info = [
            'full' => (string) $this->fullInventory,
            'exceptionType' => get_class($e),
            'errorMessage' => $e->getMessage(),
            'stackTrace' => $e->getTraceAsString()
          ];

          // log at a low level because it's no big deal
          $this->loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_ERRORS), [
            'additionalInfo' => $info,
            'method' => __METHOD__
          ]);

          //  an ongoing full sync should prevent retries of any sort
          return;
        } catch (Exception $e) {
          $info = [
            'full' => (string) $this->fullInventory,
            'exceptionType' => get_class($e),
            'errorMessage' => $e->getMessage(),
            'stackTrace' => $e->getTraceAsString()
          ];

          // log at a high level because this is unexpected
          $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_ERRORS), [
            'additionalInfo' => $info,
            'method' => __METHOD__
          ]);
        }

        if (!$this->fullInventory || (isset($syncResult) && $syncResult->isSuccessful())) {
          // only full inventory should try again
          return;
        }

        // sleep and try again
        sleep(self::SECONDS_BETWEEN_TRIES);
      }
    } finally {
      $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), [
        'additionalInfo' => [
          'full' => $this->fullInventory,
          'result' => $syncResult->toArray()
        ],
        'method' => __METHOD__
      ]);
    }
  }
}
