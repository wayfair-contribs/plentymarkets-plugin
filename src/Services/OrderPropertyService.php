<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
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

  const ORDER_PROP_KEY_TYPE_ID = 'typeId';
  const ORDER_PROP_KEY_VALUE = 'value';

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
    $this->orderRepositoryContract = $orderRepositoryContract;
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

    $externalOrderID = $this->getOrderPropertyValue($orderId, OrderPropertyType::EXTERNAL_ORDER_ID);

    if (!isset($externalOrderID) || empty($externalOrderID)) {
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

    return $externalOrderID;
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
    $warehouseId = $this->getOrderPropertyValue($orderId, OrderPropertyType::WAREHOUSE);
    $mapping = null;

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
   * NOTE: OrderPropertyRepositoryContract was retired in the plentymarkets backend in 2020
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

    $props = $plentyOrder->properties;

    if (isset($props) && is_array($props) && !empty($props))
    {
      return $props;
    }

    throw new \Exception("Order is missing properties: " . $orderId)
  }

  /**
   * Get the value for an order's property
   * NOTE: OrderPropertyRepositoryContract was retired in the plentymarkets backend in 2020
   *
   * @param integer $orderId
   * @param integer $propertyType
   * @return mixed
   */
  function getOrderPropertyValue(int $orderId, int $propertyType)
  {
    $orderProperties = $this->getAllOrderProperties($orderId);

    if (!isset($orderProperties) || empty($orderProperties)) {
      return null;
    }

    foreach ($orderProperties as $prop) {
      if ($prop[self::ORDER_PROP_KEY_TYPE_ID] == $propertyType) {
        return $prop[self::ORDER_PROP_KEY_VALUE];
      }
    }

    return null;
  }
}
