<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\ShipNotice;

/**
 * Request DTO for sending ASN message.
 * Class RequestDTO
 *
 * @package Wayfair\Core\Dto\ShipNotice
 */
class RequestDTO {
  /**
   * @var string
   */
  private $poNumber;

  /**
   * @var string
   */
  private $supplierId;

  /**
   * @var integer
   */
  private $packageCount;

  /**
   * @var float
   */
  private $weight;

  /**
   * @var float
   */
  private $volume;

  /**
   * @var string
   */
  private $carrierCode;

  /**
   * @var string
   */
  private $shipSpeed;

  /**
   * @var string
   */
  private $trackingNumber;

  /**
   * @var string
   */
  private $shipDate;

  /**
   * @var ShipNoticeAddressDTO
   */
  private $sourceAddress;


  /**
   * @var ShipNoticeAddressDTO
   */
  private $destinationAddress;

  /**
   * @var array
   */
  private $smallParcelShipments;

  /**
   * @return string
   */
  public function getPoNumber() {
    return $this->poNumber;
  }

  /**
   * @param string $poNumber
   */
  public function setPoNumber($poNumber) {
    $this->poNumber = $poNumber;
  }

  /**
   * @return string
   */
  public function getSupplierId() {
    return $this->supplierId;
  }

  /**
   * @param string $supplierId
   */
  public function setSupplierId($supplierId) {
    $this->supplierId = $supplierId;
  }

  /**
   * @return int
   */
  public function getPackageCount() {
    return $this->packageCount;
  }

  /**
   * @param int $packageCount
   */
  public function setPackageCount($packageCount) {
    $this->packageCount = $packageCount;
  }

  /**
   * @return float
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * @param float $weight
   */
  public function setWeight($weight) {
    $this->weight = $weight;
  }

  /**
   * @return float
   */
  public function getVolume() {
    return $this->volume;
  }

  /**
   * @param float $volume
   */
  public function setVolume($volume) {
    $this->volume = $volume;
  }

  /**
   * @return string
   */
  public function getCarrierCode() {
    return $this->carrierCode;
  }

  /**
   * @param string $carrierCode
   */
  public function setCarrierCode($carrierCode) {
    $this->carrierCode = $carrierCode;
  }

  /**
   * @return string
   */
  public function getShipSpeed() {
    return $this->shipSpeed;
  }

  /**
   * @param string $shipSpeed
   */
  public function setShipSpeed($shipSpeed) {
    $this->shipSpeed = $shipSpeed;
  }

  /**
   * @return string
   */
  public function getTrackingNumber() {
    return $this->trackingNumber;
  }

  /**
   * @param string $trackingNumber
   */
  public function setTrackingNumber($trackingNumber) {
    $this->trackingNumber = $trackingNumber;
  }

  /**
   * @return string
   */
  public function getShipDate() {
    return $this->shipDate;
  }

  /**
   * @param string $shipDate
   */
  public function setShipDate($shipDate) {
    $this->shipDate = $shipDate;
  }

  /**
   * @return ShipNoticeAddressDTO
   */
  public function getSourceAddress() {
    return $this->sourceAddress;
  }

  /**
   * @param ShipNoticeAddressDTO $sourceAddress
   */
  public function setSourceAddress($sourceAddress) {
    $this->sourceAddress = $sourceAddress;
  }

  /**
   * @return ShipNoticeAddressDTO
   */
  public function getDestinationAddress() {
    return $this->destinationAddress;
  }

  /**
   * @param ShipNoticeAddressDTO $destinationAddress
   */
  public function setDestinationAddress($destinationAddress) {
    $this->destinationAddress = $destinationAddress;
  }

  /**
   * @return array
   */
  public function getSmallParcelShipments() {
    return $this->smallParcelShipments;
  }

  /**
   * @param array $smallParcelShipments
   */
  public function setSmallParcelShipments($smallParcelShipments) {
    $this->smallParcelShipments = $smallParcelShipments;
  }


  /**
   * Convert current object to associative array.
   *
   * @return array
   */
  public function toArray(): array {
    return [
        'poNumber'             => $this->getPoNumber(),
        'supplierId'           => $this->getSupplierId(),
        'weight'               => $this->getWeight(),
        'volume'               => $this->getVolume(),
        'carrierCode'          => $this->getCarrierCode(),
        'shipSpeed'            => $this->getShipSpeed(),
        'trackingNumber'       => $this->getTrackingNumber(),
        'shipDate'             => $this->getShipDate(),
        'sourceAddress'        => $this->getSourceAddress()->toArray(),
        'destinationAddress'   => $this->getDestinationAddress()->toArray(),
        'packageCount'         => $this->getPackageCount(),
        'smallParcelShipments' => $this->getSmallParcelShipments()
    ];
  }
}
