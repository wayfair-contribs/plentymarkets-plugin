<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Exception;
use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\FullInventorySyncInProgressException;
use Wayfair\Core\Exceptions\WayfairVariationsMissingException;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryUpdateService;

class InventorySyncCron extends Cron
{
  const LOG_KEY_INVENTORY_ERRORS = 'inventoryErrors';

  const SECONDS_BETWEEN_TRIES = 600;

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
    $attempt = 0;

    try {

      while ($attempt++ <= $maxAttempts) {
        try {
          $syncResult = $this->inventoryUpdateService->sync($this->fullInventory);
        } catch (FullInventorySyncInProgressException $e) {

          // log at a low level because sync service should have mentioned it
          $this->loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_ERRORS), [
            'additionalInfo' => self::getInfoMapForException($e, $this->fullInventory),
            'method' => __METHOD__
          ]);

          //  an ongoing full sync should prevent retries of any sort
          return;
        } catch (WayfairVariationsMissingException $e) {
          // log at a low level because sync service should have mentioned it
          $this->loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_ERRORS), [
            'additionalInfo' => self::getInfoMapForException($e, $this->fullInventory),
            'method' => __METHOD__
          ]);

          // no use in retrying because there are no Variations to sync inventory for.
          return;
        } catch (Exception $e) {
          // log at a high level because this is unexpected
          $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_ERRORS), [
            'additionalInfo' => self::getInfoMapForException($e, $this->fullInventory),
            'method' => __METHOD__
          ]);

          // allow re-try as this exception could reflect a momentary issue
        }

        if (!$this->fullInventory || (isset($syncResult) && $syncResult->isSuccessful())) {
          // only full inventory should try again
          return;
        }

        if ($attempt < $maxAttempts) {
          // sleep and let loop
          sleep(self::SECONDS_BETWEEN_TRIES);
        }
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

  private static function getInfoMapForException(\Exception $e, $fullInventory)
  {
    return [
      'full' => (string) $fullInventory,
      'exceptionType' => get_class($e),
      'errorMessage' => $e->getMessage(),
      'stackTrace' => $e->getTraceAsString()
    ];
  }
}
