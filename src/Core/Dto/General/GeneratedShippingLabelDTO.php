<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class GeneratedShippingLabelDTO {
  /**
   * @var int
   */
  private $poNumber;

  /**
   * @var string
   */
  private $fullPoNumber;

  /**
   * @var int
   */
  private $numberOfLabels;

  /**
   * @var string
   */
  private $carrier;

  /**
   * @var string
   */
  private $carrierCode;

  /**
   * @var string
   */
  private $trackingNumber;

  /**
   * @return int
   */
  public function getPoNumber()
  {
    return $this->poNumber;
  }

  /**
   * @param mixed $poNumber
   *
   * @return void
   */
  public function setPoNumber($poNumber)
  {
    $this->poNumber = $poNumber;
  }

  /**
   * @return string
   */
  public function getFullPoNumber()
  {
    return $this->fullPoNumber;
  }

  /**
   * @param mixed $fullPoNumber
   *
   * @return void
   */
  public function setFullPoNumber($fullPoNumber)
  {
    $this->fullPoNumber = $fullPoNumber;
  }

  /**
   * @return int
   */
  public function getNumberOfLabels()
  {
    return $this->numberOfLabels;
  }

  /**
   * @param mixed $numberOfLabels
   *
   * @return void
   */
  public function setNumberOfLabels($numberOfLabels)
  {
    $this->numberOfLabels = $numberOfLabels;
  }

  /**
   * @return string
   */
  public function getCarrier()
  {
    return $this->carrier;
  }

  /**
   * @param mixed $carrier
   *
   * @return void
   */
  public function setCarrier($carrier)
  {
    $this->carrier = $carrier;
  }

  /**
   * @return string
   */
  public function getCarrierCode()
  {
    return $this->carrierCode;
  }

  /**
   * @param mixed $carrierCode
   *
   * @return void
   */
  public function setCarrierCode($carrierCode)
  {
    $this->carrierCode = $carrierCode;
  }

  /**
   * @return string
   */
  public function getTrackingNumber()
  {
    return $this->trackingNumber;
  }

  /**
   * @param mixed $trackingNumber
   *
   * @return void
   */
  public function setTrackingNumber($trackingNumber)
  {
    $this->trackingNumber = $trackingNumber;
  }

  /**
   * Static function to create a new Generated Shipping Label DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(GeneratedShippingLabelDTO::class);
    $dto->setPoNumber($params['poNumber'] ?? null);
    $dto->setFullPoNumber($params['fullPoNumber'] ?? null);
    $dto->setNumberOfLabels($params['numberOfLabels'] ?? null);
    $dto->setCarrier($params['carrier'] ?? null);
    $dto->setCarrierCode($params['carrierCode'] ?? null);
    $dto->setTrackingNumber($params['trackingNumber'] ?? null);
    return $dto;
  }
}
