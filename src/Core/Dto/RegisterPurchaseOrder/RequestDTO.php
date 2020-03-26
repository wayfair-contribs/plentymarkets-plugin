<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\RegisterPurchaseOrder;

use Wayfair\Core\Dto\General\ShippingUnitDTO;

class RequestDTO {

  /**
   * @var string
   */
  private $poNumber;

  /**
   * @var int
   */
  private $warehouseId;

  /**
   * @var string
   */
  private $requestForPickupDate;

  /**
   * @var ShippingUnitDTO[]
   */
  private $shippingUnits;

  /**
   * @return string
   */
  public function getPoNumber() {
    return $this->poNumber;
  }

  /**
   * @param mixed $poNumber
   *
   * @return void
   */
  public function setPoNumber($poNumber) {
    $this->poNumber = $poNumber;
  }

  /**
   * @return int
   */
  public function getWarehouseId() {
    return $this->warehouseId;
  }

  /**
   * @param mixed $warehouseId
   *
   * @return void
   */
  public function setWarehouseId($warehouseId) {
    $this->warehouseId = $warehouseId;
  }

  /**
   * @return string
   */
  public function getRequestForPickupDate() {
    return $this->requestForPickupDate;
  }

  /**
   * @param mixed $requestForPickupDate
   *
   * @return void
   */
  public function setRequestForPickupDate($requestForPickupDate) {
    $this->requestForPickupDate = $requestForPickupDate;
  }

  /**
   * @return array
   */
  public function getShippingUnits() {
    return $this->shippingUnits;
  }

  /**
   * @param mixed $shippingUnits
   *
   * @return void
   */
  public function setShippingUnits($shippingUnits) {
    $this->shippingUnits = [];
    foreach ($shippingUnits as $shippingUnit) {
      $this->shippingUnits[] = ShippingUnitDTO::createFromArray($shippingUnit);
    }
  }

  /**
   * Static function to create a new Request DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self {
    $dto = pluginApp(RequestDTO::class);
    $dto->setPoNumber($params['poNumber'] ?? null);
    $dto->setWarehouseId($params['warehouseId'] ?? null);
    $dto->setRequestForPickupDate($params['requestForPickupDate'] ?? null);
    $dto->setShippingUnits($params['shippingUnits'] ?? null);
    return $dto;
  }
}
