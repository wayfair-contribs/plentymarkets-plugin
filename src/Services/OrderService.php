<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Api\Services\AcceptOrderService;
use Wayfair\Core\Api\Services\FetchOrderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\TimeHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Repositories\PendingOrdersRepository;

class OrderService
{

  const LOG_KEY_FAILED_TO_CREATE_ORDER = 'failedToCreateOrder';
  const LOG_KEY_ORDER_CREATION_INCOMPLETE = 'orderCreatedButIncomplete';

  /**
   * @var FetchOrderService
   */
  public $fetchOrderService;

  /**
   * @var CreateOrderService
   */
  public $createOrderService;

  /**
   * @var PendingOrdersRepository
   */
  public $pendingOrdersRepository;

  /**
   * @var AcceptOrderService
   */
  public $acceptOrderService;

  /**
   * @var LoggerContract
   */
  public $loggerContract;

  /**
   * OrderService constructor.
   *
   * @param FetchOrderService       $fetchOrderService
   * @param CreateOrderService      $createOrderService
   * @param AcceptOrderService      $acceptOrderService
   * @param PendingOrdersRepository $pendingOrdersRepository
   * @param LoggerContract          $loggerContract
   */
  public function __construct(
    FetchOrderService $fetchOrderService,
    CreateOrderService $createOrderService,
    AcceptOrderService $acceptOrderService,
    PendingOrdersRepository $pendingOrdersRepository,
    LoggerContract $loggerContract
  ) {
    $this->fetchOrderService = $fetchOrderService;
    $this->createOrderService = $createOrderService;
    $this->acceptOrderService = $acceptOrderService;
    $this->pendingOrdersRepository = $pendingOrdersRepository;
    $this->loggerContract = $loggerContract;
  }

  /**
   * @param ExternalLogs $externalLogs
   * @param int          $circle
   *
   * @throws \Exception
   *
   * @return void
   */
  public function process(ExternalLogs $externalLogs, int $circle)
  {
    $timeStartFetch = TimeHelper::getMilliseconds();
    $orders = [];
    try {
      $orders = $this->fetchOrderService->fetch($circle);
      $receivedOrdersDuration = TimeHelper::getMilliseconds() - $timeStartFetch;
      $externalLogs->addPurchaseOrderLog('PO fetching', 'poReceived', count($orders), $receivedOrdersDuration);
    } catch (\Exception $e) {
      $receiveFailedOrdersDuration = TimeHelper::getMilliseconds() - $timeStartFetch;
      $externalLogs->addErrorLog($e->getMessage());
      $externalLogs->addPurchaseOrderLog('PO fetching failed', 'poReceiveFailed', 0, $receiveFailedOrdersDuration);
      return;
    }

    $timeStartCreation = TimeHelper::getMilliseconds();
    $createdOrdersCount = 0;
    $failedOrdersCount = 0;
    foreach ($orders as $order) {
      $plentyOrderId = 0;
      try {
        $plentyOrderId = $this->createOrderService->create($order);
        if ($plentyOrderId < 0) {
          $externalLogs->addErrorLog('Order already exists, PO: ' . $order->getPoNumber());
        } else if ($plentyOrderId > 0) {
          $createdOrdersCount++;
        }
      } catch (\Exception $e) {
        // TODO: determine if presence of order ID means this failure count should NOT be incremented
        $failedOrdersCount++;

        $logInfo = [
          'exception' => $e,
          'exceptionType' => get_class($e),
          'message' => $e->getMessage(),
          'stackTrace' => $e->getTrace(),
          'po' => $order->getPoNumber(),
        ];

        $logKey = self::LOG_KEY_FAILED_TO_CREATE_ORDER;
        if ($plentyOrderId > 0) {

          $logInfo['plentyOrder'] = $plentyOrderId;

          // TODO: determine if an incomplete order should be deleted from Plentymarkets
          $logKey = self::LOG_KEY_ORDER_CREATION_INCOMPLETE;
        }

        $this->loggerContract->error(
          TranslationHelper::getLoggerKey($logKey),
          [
            'additionalInfo' => $logInfo,
            'method' => __METHOD__
          ]
        );

        $externalLogs->addErrorLog('Exception caught while creating an order, PO: ' . $order->getPoNumber() .
          ' Order:' . $plentyOrderId .
          ' ' . get_class($e) . ': ' . $e->getMessage());
      }
    }
    $createdOrdersDuration = TimeHelper::getMilliseconds() - $timeStartCreation;
    $externalLogs->addPurchaseOrderLog('PO creating', 'poCreated', $createdOrdersCount, $createdOrdersDuration);
    $externalLogs->addPurchaseOrderLog('PO creating', 'poFailed', $failedOrdersCount, $createdOrdersDuration);
    if (count($orders)) {
      $this->process($externalLogs, $circle + 1);
    }

    // do NOT send $externalLogs to Wayfair.
    // The caller MUST send the external logs, due to the recursive nature of this function.
  }

  /**
   * @param ExternalLogs $externalLogs
   * @param int          $circle
   *
   * @throws \Exception
   *
   * @return void
   */
  public function accept(ExternalLogs $externalLogs, int $circle)
  {
    $ms = TimeHelper::getMilliseconds();
    $pendingOrders = $this->pendingOrdersRepository->getAll($circle);
    $poAcceptDuration = TimeHelper::getMilliseconds() - $ms;
    $externalLogs->addPurchaseOrderLog('PO accepting', 'poAccept', count($pendingOrders), $poAcceptDuration);
    $ms = TimeHelper::getMilliseconds();
    $acceptedOrders = [];
    foreach ($pendingOrders as $pendingOrder) {
      $poNum = $pendingOrder->poNum;
      $items = \json_decode($pendingOrder->items, true);
      if ($this->acceptOrderService->accept($poNum, $items)) {
        $acceptedOrders[] = $poNum;
      } else {
        $externalLogs->addPurchaseOrderLog('PO accept failed', 'poAcceptFailed', 1, TimeHelper::getMilliseconds() - $ms);
        $externalLogs->addErrorLog('Failed to accept PO: ' . $poNum);
        $this->pendingOrdersRepository->incrementAttempts($poNum);
      }
    }
    $poAcceptedDuration = TimeHelper::getMilliseconds() - $ms;
    if (count($pendingOrders)) {
      $externalLogs->addPurchaseOrderLog('PO accepted', 'poAccepted', count($acceptedOrders), $poAcceptedDuration);
    }
    if (count($acceptedOrders)) {
      $this->pendingOrdersRepository->delete($acceptedOrders);
    }
    if (count($pendingOrders) === PendingOrdersRepository::GET_LIMIT) {
      $this->accept($externalLogs, $circle + 1);
    }
  }
}
