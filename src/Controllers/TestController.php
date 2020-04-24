<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Plugin\Controller;
use Wayfair\Core\Api\Services\FetchOrderService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Repositories\PendingOrdersRepository;
use Wayfair\Services\OrderService;
use Plenty\Plugin\Http\Request;
use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;

class TestController extends Controller {

  /**
   * @param OrderService     $orderService
   * @param LogSenderService $logSenderService
   *
   * @return string
   * @throws \Exception
   */
  public function fetchAndCreateOrders(OrderService $orderService, LogSenderService $logSenderService) {
    $externalLogs = pluginApp(ExternalLogs::class);
    $orderService->process($externalLogs, 1);
    if (count($externalLogs->getLogs())) {
      $logSenderService->execute($externalLogs->getLogs());
    }
    echo 'Done';
  }

  /**
   * @return string
   */
  public function deleteOrders(
    Request $request,
    OrderRepositoryContract $orderRepositoryContract,
    OrderShippingPackageRepositoryContract $orderShippingPackageRepositoryContract
  ) {
    $id = $request->input('id');
    $orderShippingPackages = $orderShippingPackageRepositoryContract->listOrderShippingPackages($id);
    echo "First function -------------------------------- \n";
    foreach ($orderShippingPackages as $orderShippingPackage) {
      echo $orderShippingPackage->packageNumber . "\n";
    }
    echo "Second function -------------------------------- \n";
    $orderTrackingNumbers = $orderRepositoryContract->getPackageNumbers($id);
    echo json_encode($orderTrackingNumbers);
    echo "\n -------------------------------- \n";


    $orderRepositoryContract = pluginApp(OrderRepositoryContract::class);
    $orderList = $orderRepositoryContract->searchOrders(1, 250);
    foreach ($orderList->getResult() as $order) {
      $orderRepositoryContract->deleteOrder($order['id']);
    }
    return 'done';
  }

  /**
   * @param KeyValueRepository $keyValue
   *
   * @return array
   */
  public function showKeyValueAll(KeyValueRepository $keyValue, ConfigHelper $config) {
    return $keyValue->getAll();
  }

  /**
   * @param Request $request
   *
   * @return array
   */
  public function showPendingOrders(Request $request) {
    $circle = $request->input('circle');
    $pendingOrdersRepository = pluginApp(PendingOrdersRepository::class);
    return $pendingOrdersRepository->getAll($circle);
  }

  /**
   * @param Request $request
   *
   * @return array
   */
  public function deletePendingOrders(Request $request) {
    $pendingOrdersRepository = pluginApp(PendingOrdersRepository::class);
    return $pendingOrdersRepository->deleteAll();
  }

  /**
   * @param OrderService $orderService
   *
   * @return string
   * @throws \Exception
   */
  public function acceptOrders(Request $request) {
    $circle = $request->input('circle');
    $orderService = pluginApp(OrderService::class);
    $externalLogs = pluginApp(ExternalLogs::class);
    $orderService->accept($externalLogs, (int)$circle);
    return 'done';
  }

  /**
   * @param PaymentMethodRepositoryContract $paymentMethodRepository
   *
   * @return array
   * @throws \Exception
   */
  public function paymentMethods(PaymentMethodRepositoryContract $paymentMethodRepository) {
    return $paymentMethodRepository->all();
  }

  /**
   * @param KeyValueRepository $keyValue
   *
   * @return string
   * @throws \Exception
   */
  public function updateFullInventoryStatus(KeyValueRepository $keyValue) {
    $keyValue->putOrReplace(ConfigHelperContract::FULL_INVENTORY_CRON_STATUS, ConfigHelperContract::FULL_INVENTORY_CRON_IDLE);
    return 'Done';
  }
}