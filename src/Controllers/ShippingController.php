<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Services\ShipmentRegisterService;

/**
 * Class ShippingController
 *
 * @package Wayfair\Controllers
 */
class ShippingController extends Controller
{
  /**
   * @var ShipmentRegisterService
   */
  private $shipmentRegisterService;

  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * ShippingController constructor.
   *
   * @param ShipmentRegisterService $shipmentRegisterService
   */
  public function __construct(ShipmentRegisterService $shipmentRegisterService, LoggerContract $loggerContract)
  {
    $this->shipmentRegisterService = $shipmentRegisterService;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Action to register order ids to Wayfair shipment.
   *
   * @param Request $request
   * @param array   $orderIds
   *
   * @return array
   * @throws \Exception
   */
  public function registerShipments(Request $request, array $orderIds): array
  {
    $orderIds = $this->processOrderIds($request, $orderIds);
    $this->loggerContract
        ->info(
          TranslationHelper::getLoggerKey('registerShipmentForOrders'),
          [
            'additionalInfo' => ['orderIds' => $orderIds],
            'method' => __METHOD__
          ]
        );

    return $this->shipmentRegisterService->register($orderIds);
  }

  /**
   * Get already generated labels and return to shipping center interface
   *
   * @param Request $request
   * @param mixed   $orderIds
   *
   * @return array
   */
  public function getLabels(Request $request, $orderIds): array
  {
    $orderIds = $this->processOrderIds($request, $orderIds);
    $this->loggerContract
        ->info(
          TranslationHelper::getLoggerKey('getGeneratedLabels'),
          [
            'additionalInfo' => ['orderIds' => $orderIds],
            'method' => __METHOD__
          ]
        );

    return $this->shipmentRegisterService->getGeneratedLabels($orderIds);
  }

  /**
   * Action for unregister order ids from Wayfair shipment.
   *
   * @param Request $request
   * @param array   $orderIds
   *
   * @return array
   */
  public function deleteShipments(Request $request, array $orderIds): array
  {
    $orderIds = $this->processOrderIds($request, $orderIds);
    $this->loggerContract
        ->info(
          TranslationHelper::getLoggerKey('deleteShipmentForOrders'),
          [
            'additionalInfo' => ['orderIds' => $orderIds],
            'method' => __METHOD__
          ]
        );

    return $this->shipmentRegisterService->unregister($orderIds);
  }

  /**
   * @param Request $request
   * @param mixed   $orderIds (can be either a numeric or an array)
   *
   * @return array
   */
  private function processOrderIds(Request $request, $orderIds): array
  {
    if (is_numeric($orderIds)) {
      $orderIds = [$orderIds];
    } elseif (!is_array($orderIds)) {
      $orderIds = $request->get('orderIds');
    }

    return $orderIds;
  }
}
