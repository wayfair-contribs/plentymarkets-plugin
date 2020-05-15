<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Order\Property\Contracts\OrderPropertyRepositoryContract;
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
   * @var OrderPropertyRepositoryContract
   */
  private $orderPropertyRepositoryContract;

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
   * @param OrderPropertyRepositoryContract       $orderPropertyRepositoryContract
   * @param WarehouseSupplierRepository           $warehouseSupplierRepository
   * @param ShippingInformationRepositoryContract $shippingInformationRepositoryContract
   * @param LoggerContract                        $loggerContract
   */
  public function __construct(
    OrderPropertyRepositoryContract $orderPropertyRepositoryContract,
    WarehouseSupplierRepository $warehouseSupplierRepository,
    ShippingInformationRepositoryContract $shippingInformationRepositoryContract,
    LoggerContract $loggerContract
  ) {
    $this->orderPropertyRepositoryContract = $orderPropertyRepositoryContract;
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
    $orderProperties = $this->orderPropertyRepositoryContract->findByOrderId($orderId, OrderPropertyType::EXTERNAL_ORDER_ID);
    if (empty($orderProperties) || empty($orderProperties[0]->value)) {
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

    return $orderProperties[0]->value;
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
    $orderProperties = $this->orderPropertyRepositoryContract->findByOrderId($orderId, OrderPropertyType::WAREHOUSE);
    if (empty($orderProperties) || empty($orderProperties[0]->value)) {
      $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_WAREHOUSE_ID_NOT_FOUND),
            [
              'additionalInfo' => [
                'orderId' => $orderId
              ],
              'method' => __METHOD__,
              'referenceType' => 'orderId',
              'referenceValue' => $orderId
            ]
          );

      return '';
    }

    $warehouseId = $orderProperties[0]->value;
    $mapping = $this->warehouseSupplierRepository->findByWarehouseId($warehouseId);
    if (! isset($mapping) || empty($mapping->supplierId)) {
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
    $orderProperties = $this->orderPropertyRepositoryContract->findByOrderId($orderId);

    return $orderProperties;
  }
}
