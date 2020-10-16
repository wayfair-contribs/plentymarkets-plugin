<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Exception;
use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\InventoryUpdateService;

class InventorySyncCron extends Cron
{
  const LOG_KEY_INVENTORY_ERRORS = 'inventoryErrors';

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
    try {
      $syncResult = $this->inventoryUpdateService->sync($this->fullInventory);
    } catch (Exception $e) {
      // log at a high level because this is unexpected
      $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_ERRORS), [
        'additionalInfo' => self::getInfoMapForException($e, $this->fullInventory),
        'method' => __METHOD__
      ]);
    } finally {
      $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), [
        'additionalInfo' => [
          'full' => $this->fullInventory,
          'result' => $syncResult
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
