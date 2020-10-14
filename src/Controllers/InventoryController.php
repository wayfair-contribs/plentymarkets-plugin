<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
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
    parent::__construct();
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

  public function sync(Request $request, Response $response)
  {
    try {
      $dataIn = $request->input('data');

      $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_IN), [
        'additionalInfo' => ['payloadIn' => json_encode($dataIn)],
        'method'         => __METHOD__
      ]);

      $fullInventory = isset($dataIn) && $dataIn['full'];

      $syncResults = $this->inventoryUpdateService->sync($fullInventory);
      $payloadOut = $syncResults->toArray();

      $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
        'additionalInfo' => [
          'payloadOut' => $payloadOut
        ],
        'method'         => __METHOD__
      ]);

      return $response->json($payloadOut);
    } catch (\Exception $e) {

      return $response->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
    }
  }
}
