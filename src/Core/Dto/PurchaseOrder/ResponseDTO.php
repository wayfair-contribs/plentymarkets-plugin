<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\PurchaseOrder;

use Wayfair\Core\Dto\General\BillingInfoDTO;
use Wayfair\Core\Dto\General\WarehouseDTO;
use Wayfair\Core\Dto\General\ProductDTO;
use Wayfair\Core\Dto\General\AddressDTO;

class ResponseDTO {
  /**
   * @var string
   */
  private $storePrefix;

  /**
   * @var string
   */
  private $poNumber;

  /**
   * @var string
   */
  private $poDate;

  /**
   * @var string
   */
  private $estimatedShipDate;

  /**
   * @var string
   */
  private $deliveryMethodCode;

  /**
   * @var string
   */
  private $customerName;

  /**
   * @var string
   */
  private $customerAddress1;

  /**
   * @var string
   */
  private $customerAddress2;

  /**
   * @var string
   */
  private $customerCity;

  /**
   * @var string
   */
  private $customerState;

  /**
   * @var string
   */
  private $customerPostalCode;

  /**
   * @var string
   */
  private $salesChannelName;

  /**
   * @var string
   */
  private $orderType;

  /**
   * @var string
   */
  private $packingSlipUrl;

  /**
   * @var WarehouseDTO
   */
  private $warehouse;

  /**
   * @var ProductDTO[]
   */
  private $products;

  /**
   * @var AddressDTO
   */
  private $shipTo;

  /**
   * @var BillingInfoDTO
   */
  private $billingInfo;

  /**
   * @return string
   */
  public function getStorePrefix() {
    return $this->storePrefix;
  }

  /**
   * @param mixed $storePrefix
   *
   * @return void
   */
  public function setStorePrefix($storePrefix) {
    $this->storePrefix = $storePrefix;
  }

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
   * @return string
   */
  public function getPoDate() {
    return $this->poDate;
  }

  /**
   * @param mixed $poDate
   *
   * @return void
   */
  public function setPoDate($poDate) {
    $this->poDate = $poDate;
  }

  /**
   * @return string
   */
  public function getEstimatedShipDate() {
    return $this->estimatedShipDate;
  }

  /**
   * @param mixed $estimatedShipDate
   *
   * @return void
   */
  public function setEstimatedShipDate($estimatedShipDate) {
    $this->estimatedShipDate = $estimatedShipDate;
  }

  /**
   * @return string
   */
  public function getDeliveryMethodCode() {
    return $this->deliveryMethodCode;
  }

  /**
   * @param mixed $deliveryMethodCode
   *
   * @return void
   */
  public function setDeliveryMethodCode($deliveryMethodCode) {
    $this->deliveryMethodCode = $deliveryMethodCode;
  }

  /**
   * @return string
   */
  public function getCustomerName() {
    return $this->customerName;
  }

  /**
   * @param mixed $customerName
   *
   * @return void
   */
  public function setCustomerName($customerName) {
    $this->customerName = $customerName;
  }

  /**
   * @return string
   */
  public function getCustomerAddress1() {
    return $this->customerAddress1;
  }

  /**
   * @param mixed $customerAddress1
   *
   * @return void
   */
  public function setCustomerAddress1($customerAddress1) {
    $this->customerAddress1 = $customerAddress1;
  }

  /**
   * @return string
   */
  public function getCustomerAddress2() {
    return $this->customerAddress2;
  }

  /**
   * @param mixed $customerAddress2
   *
   * @return void
   */
  public function setCustomerAddress2($customerAddress2) {
    $this->customerAddress2 = $customerAddress2;
  }

  /**
   * @return string
   */
  public function getCustomerCity() {
    return $this->customerCity;
  }

  /**
   * @param mixed $customerCity
   *
   * @return void
   */
  public function setCustomerCity($customerCity) {
    $this->customerCity = $customerCity;
  }

  /**
   * @return string
   */
  public function getCustomerState() {
    return $this->customerState;
  }

  /**
   * @param mixed $customerState
   *
   * @return void
   */
  public function setCustomerState($customerState) {
    $this->customerState = $customerState;
  }

  /**
   * @return string
   */
  public function getCustomerPostalCode() {
    return $this->customerPostalCode;
  }

  /**
   * @param mixed $customerPostalCode
   *
   * @return void
   */
  public function setCustomerPostalCode($customerPostalCode) {
    $this->customerPostalCode = $customerPostalCode;
  }

  /**
   * @return string
   */
  public function getSalesChannelName() {
    return $this->salesChannelName;
  }

  /**
   * @param mixed $salesChannelName
   *
   * @return void
   */
  public function setSalesChannelName($salesChannelName) {
    $this->salesChannelName = $salesChannelName;
  }

  /**
   * @return string
   */
  public function getOrderType() {
    return $this->orderType;
  }

  /**
   * @param mixed $orderType
   *
   * @return void
   */
  public function setOrderType($orderType) {
    $this->orderType = $orderType;
  }

  /**
   * @return string
   */
  public function getPackingSlipUrl() {
    return $this->packingSlipUrl;
  }

  /**
   * @param mixed $packingSlipUrl
   *
   * @return void
   */
  public function setPackingSlipUrl($packingSlipUrl) {
    $this->packingSlipUrl = $packingSlipUrl;
  }

  /**
   * @return WarehouseDTO
   */
  public function getWarehouse() {
    return $this->warehouse;
  }

  /**
   * @param mixed $warehouse
   *
   * @return void
   */
  public function setWarehouse($warehouse) {
    $this->warehouse = WarehouseDTO::createFromArray($warehouse);
  }

  /**
   * @return array
   */
  public function getProducts() {
    return $this->products;
  }

  /**
   * @param mixed $products
   *
   * @return void
   */
  public function setProducts($products) {
    $this->products = [];
    foreach ($products as $key => $product) {
      $this->products[] = ProductDTO::createFromArray($product);
    }
  }

  /**
   * @return AddressDTO
   */
  public function getShipTo() {
    return $this->shipTo;
  }

  /**
   * @param mixed $shipTo
   *
   * @return void
   */
  public function setShipTo($shipTo) {
    $this->shipTo = AddressDTO::createFromArray($shipTo);
  }

  /**
   * @return BillingInfoDTO
   */
  public function getBillingInfo() {
    return $this->billingInfo;
  }

  /**
   * @param mixed $billingInfo
   *
   * @return void
   */
  public function setBillingInfo($billingInfo) {
    $this->billingInfo = BillingInfoDTO::createFromArray($billingInfo);
  }

  /**
   * Static function to create a new Response DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self {
    $dto = pluginApp(ResponseDTO::class);
    $dto->setStorePrefix($params['storePrefix'] ?? null);
    $dto->setPoNumber($params['poNumber'] ?? null);
    $dto->setPoDate($params['poDate'] ?? null);
    $dto->setEstimatedShipDate($params['estimatedShipDate'] ?? null);
    $dto->setDeliveryMethodCode($params['deliveryMethodCode'] ?? null);
    $dto->setCustomerName($params['customerName'] ?? null);
    $dto->setCustomerAddress1($params['customerAddress1'] ?? null);
    $dto->setCustomerAddress2($params['customerAddress2'] ?? null);
    $dto->setCustomerCity($params['customerCity'] ?? null);
    $dto->setCustomerState($params['customerState'] ?? null);
    $dto->setCustomerPostalCode($params['customerPostalCode'] ?? null);
    $dto->setSalesChannelName($params['salesChannelName'] ?? null);
    $dto->setOrderType($params['orderType'] ?? null);
    $dto->setPackingSlipUrl($params['packingSlipUrl'] ?? null);
    $dto->setWarehouse($params['warehouse'] ?? []);
    $dto->setProducts($params['products'] ?? []);
    $dto->setShipTo($params['shipTo'] ?? []);
    $dto->setBillingInfo($params['billingInfo'] ?? []);
    return $dto;
  }
}
