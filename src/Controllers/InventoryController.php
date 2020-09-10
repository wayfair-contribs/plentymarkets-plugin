<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Services\InventoryStatusService;
use Wayfair\Services\InventoryUpdateService;
use Wayfair\Helpers\TranslationHelper;

class InventoryController extends Controller
{
  const LOG_KEY_CONTROLLER_IN = "controllerInput";
  const LOG_KEY_CONTROLLER_OUT = "controllerOutput";

  /** @var InventoryUpdateService */
  private $inventoryUpdateService;

  /** @var InventoryStatusService */
  private $inventoryStatusService;

  /** @var LoggerContract */
  private $logger;

  /**
   * InventoryController constructor.
   *
   * @param InventoryUpdateService $inventoryUpdateService
   * @param InventoryStatusService $inventoryStatusService
   * @param LoggerContract $logger
   */
  public function __construct(
    InventoryUpdateService $inventoryUpdateService,
    InventoryStatusService $inventoryStatusService,
    LoggerContract $logger
  ) {
    $this->inventoryUpdateService = $inventoryUpdateService;
    $this->inventoryStatusService = $inventoryStatusService;
    $this->logger = $logger;
  }

  /**
   * @return string
   */
  public function getState()
  {
    $dataOut = json_encode($this->inventoryStatusService->getServiceState());

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => [
        'payloadOut' => $dataOut
      ],
      'method'         => __METHOD__
    ]);

    return $dataOut;
  }

  /**
   * @return void
   */
  public function clearState()
  {
    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_IN), [
      'additionalInfo' => [
        'payloadIn' => ''
      ],
      'method'         => __METHOD__
    ]);

    $this->inventoryStatusService->clearState(true);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => [
        'payloadOut' => ''
      ],
      'method'         => __METHOD__
    ]);
  }
}
