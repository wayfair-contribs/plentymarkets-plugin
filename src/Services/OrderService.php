<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Api\Services\AcceptOrderService;
use Wayfair\Core\Api\Services\FetchOrderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\GraphQLQueryException;
use Wayfair\Core\Helpers\TimeHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Repositories\PendingOrdersRepository;

class OrderService {
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
  public function process(ExternalLogs $externalLogs, int $circle) {
    $ms = TimeHelper::getMilliseconds();
    try {
      $orders = $this->fetchOrderService->fetch($circle);
      $this->loggerContract->debug('OrderService', ['additionalInfo' => $orders, 'method' => __METHOD__]);
      $receivedOrdersDuration = TimeHelper::getMilliseconds() - $ms;
      $externalLogs->addPurchaseOrderLog('PO fetching','poReceived', count($orders), $receivedOrdersDuration);
    } catch (\Exception $e) {
      $receiveFailedOrdersDuration = TimeHelper::getMilliseconds() - $ms;
      $externalLogs->addErrorLog($e->getMessage());
      $externalLogs->addPurchaseOrderLog('PO fetching failed','poReceiveFailed', 0, $receiveFailedOrdersDuration);
      return;
    }
    $ms = TimeHelper::getMilliseconds();
    $createdOrdersCount = 0;
    $failedOrdersCount = 0;
    foreach ($orders as $order) {
      try {
        $orderCreated = $this->createOrderService->create($order);
        if ($orderCreated) {
          $createdOrdersCount++;
        } else {
          $externalLogs->addErrorLog('Order already exists, PO: ' . $order->getPoNumber());
        }
      } catch (\Exception $e) {
        $failedOrdersCount++;
        $externalLogs->addErrorLog('Failed to create an order, PO: ' . $order->getPoNumber() .
          ' ' . get_class($e) . ': ' . $e->getMessage());
      }
    }
    $createdOrdersDuration = TimeHelper::getMilliseconds() - $ms;
    $externalLogs->addPurchaseOrderLog('PO creating','poCreated', $createdOrdersCount, $createdOrdersDuration);
    $externalLogs->addPurchaseOrderLog('PO creating','poFailed', $failedOrdersCount, $createdOrdersDuration);
    if (count($orders)) {
      $this->process($externalLogs, $circle + 1);
    }
  }

  /**
   * @param ExternalLogs $externalLogs
   * @param int          $circle
   *
   * @throws \Exception
   *
   * @return void
   */
  public function accept(ExternalLogs $externalLogs, int $circle) {
    $ms = TimeHelper::getMilliseconds();
    $pendingOrders = $this->pendingOrdersRepository->getAll($circle);
    $poAcceptDuration = TimeHelper::getMilliseconds() - $ms;
    $externalLogs->addPurchaseOrderLog('PO accepting','poAccept', count($pendingOrders), $poAcceptDuration);
    $ms = TimeHelper::getMilliseconds();
    $acceptedOrders = [];
    foreach ($pendingOrders as $pendingOrder) {
      $poNum = $pendingOrder->poNum;
      $items = \json_decode($pendingOrder->items, true);
      if ($this->acceptOrderService->accept($poNum, $items)) {
        $acceptedOrders[] = $poNum;
      } else {
        $externalLogs->addPurchaseOrderLog('PO accept failed','poAcceptFailed', 1, TimeHelper::getMilliseconds() - $ms);
        $externalLogs->addErrorLog('Failed to accept PO: ' . $poNum);
        $this->pendingOrdersRepository->incrementAttempts($poNum);
      }
    }
    $poAcceptedDuration = TimeHelper::getMilliseconds() - $ms;
    if (count($pendingOrders)) {
      $externalLogs->addPurchaseOrderLog('PO accepted','poAccepted', count($acceptedOrders), $poAcceptedDuration);
    }
    if (count($acceptedOrders)) {
      $this->pendingOrdersRepository->delete($acceptedOrders);
    }
    if (count($pendingOrders) === PendingOrdersRepository::GET_LIMIT) {
      $this->accept($externalLogs, $circle + 1);
    }
  }
}
