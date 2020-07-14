<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Order\Shipping\Information\Contracts\ShippingInformationRepositoryContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\WarehouseSupplierRepository;

/**
 * An order property service class to get related information
 * Class OrderPropertyService
 *
 * @package Wayfair\Services
 */
class OrderPropertyService
{

  const LOG_KEY_CANNOT_OBTAIN_PO_NUMBER = 'obtainPoNumber';
  const LOG_KEY_WAREHOUSE_ID_NOT_FOUND = 'warehouseIdNotFound';
  const LOG_KEY_NO_SUPPLIER_ID_FOR_WAREHOUSE = 'noSupplierIDForWarehouse';

  /**
   * @var OrderRepositoryContract
   */
  private $orderRepositoryContract;

  /**
   * @var WarehouseSupplierRepository
   */
  private $warehouseSupplierRepository;

  /**
   * @var ShippingInformationRepositoryContract
   */
  private $shippingInformationRepositoryContract;
  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * OrderPropertyService constructor.
   *
   * @param OrderRepositoryContract               $orderRepositoryContract
   * @param WarehouseSupplierRepository           $warehouseSupplierRepository
   * @param ShippingInformationRepositoryContract $shippingInformationRepositoryContract
   * @param LoggerContract                        $loggerContract
   */
  public function __construct(
    OrderRepositoryContract $orderRepositoryContract,
    WarehouseSupplierRepository $warehouseSupplierRepository,
    ShippingInformationRepositoryContract $shippingInformationRepositoryContract,
    LoggerContract $loggerContract
  ) {
    $this->OrderRepositoryContract = $orderRepositoryContract;
    $this->warehouseSupplierRepository = $warehouseSupplierRepository;
    $this->shippingInformationRepositoryContract = $shippingInformationRepositoryContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Get PoNumber for Order Id
   *
   * @param int $orderId
   *
   * @return string
   */
  public function getCheckedPoNumber(int $orderId): string
  {
    $plentyOrder = $this->orderRepositoryContract->findOrderById($orderId);

    if (!isset($plentyOrder)) {
      throw new \Exception("Order not found : " . (string)$orderId);
    }

    $orderProperties = $plentyOrder->properties;

    if (isset($orderProperties) && !empty($orderProperties) && array_key_exists(OrderPropertyType::EXTERNAL_ORDER_ID, $orderProperties)) {

      // TODO: add check of PO number against Wayfair API in future release?
      return $orderProperties[OrderPropertyType::EXTERNAL_ORDER_ID];
    }

    $this->loggerContract
      ->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_CANNOT_OBTAIN_PO_NUMBER),
        [
          'method' => __METHOD__,
          'referenceType' => 'orderId',
          'referenceValue' => $orderId
        ]
      );

    return '';
  }

  /**
   * Get Wayfair warehouse id; (it is the same as supplierId)
   *
   * @param int $orderId
   *
   * @return string
   */
  public function getWarehouseId(int $orderId): string
  {
    $plentyOrder = $this->orderRepositoryContract->findOrderById($orderId);

    if (!isset($plentyOrder)) {
      throw new \Exception("Order not found : " . (string)$orderId);
    }

    $orderProperties = $plentyOrder->properties;

    $warehouseId = null;
    $mapping = null;

    if (isset($orderProperties) && !empty($orderProperties) && array_key_exists(OrderPropertyType::WAREHOUSE, $orderProperties)) {
      $warehouseId = $orderProperties[OrderPropertyType::WAREHOUSE];
    }

    if (isset($warehouseId) && !empty($warehouseId)) {
      $mapping = $this->warehouseSupplierRepository->findByWarehouseId($warehouseId);
    }

    if (!isset($mapping) || empty($mapping->supplierId)) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SUPPLIER_ID_FOR_WAREHOUSE),
          [
            'method' => __METHOD__,
            'referenceType' => 'warehouseId',
            'referenceValue' => $warehouseId
          ]
        );
      return '';
    }

    return $mapping->supplierId;
  }

  /**
   * Get all order properties by order id.
   *
   * @param int $orderId
   *
   * @return array
   */
  public function getAllOrderProperties(int $orderId): array
  {
    $plentyOrder = $this->orderRepositoryContract->findOrderById($orderId);

    if (!isset($plentyOrder)) {
      throw new \Exception("Order not found : " . (string)$orderId);
    }

    return $plentyOrder->properties;
  }
}
