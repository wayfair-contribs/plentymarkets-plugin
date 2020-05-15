<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class ShippingUnitDTO {
  /**
   * @var string
   */
  private $partNumber;

  /**
   * @var string
   */
  private $unitType;

  /**
   * @var WeightDTO
   */
  private $weight;

  /**
   * @var DimensionDTO
   */
  private $dimensions;

  /**
   * @var string
   */
  private $freightClass;

  /**
   * @var PalletDTO
   */
  private $palletInfo;

  /**
   * @var int
   */
  private $groupIdentifier;

  /**
   * @var int
   */
  private $sequenceIdentifier;

  /**
   * @return string
   */
  public function getPartNumber()
  {
    return $this->partNumber;
  }

  /**
   * @param mixed $partNumber
   *
   * @return void
   */
  public function setPartNumber($partNumber)
  {
    $this->partNumber = $partNumber;
  }

  /**
   * @return string
   */
  public function getUnitType()
  {
    return $this->unitType;
  }

  /**
   * @param mixed $unitType
   *
   * @return void
   */
  public function setUnitType($unitType)
  {
    $this->unitType = $unitType; // should be in Constants::AVAILABLE_UNIT_TYPES
  }

  /**
   * @return WeightDTO
   */
  public function getWeight()
  {
    return $this->weight;
  }

  /**
   * @param mixed $weight
   *
   * @return void
   */
  public function setWeight($weight)
  {
    $this->weight = WeightDTO::createFromArray($weight);
  }

  /**
   * @return DimensionDTO
   */
  public function getDimensions()
  {
    return $this->dimensions;
  }

  /**
   * @param mixed $dimensions
   *
   * @return void
   */
  public function setDimensions($dimensions)
  {
    $this->dimensions = DimensionDTO::createFromArray($dimensions);
  }

  /**
   * @return string
   */
  public function getFreightClass()
  {
    return $this->freightClass;
  }

  /**
   * @param mixed $freightClass
   *
   * @return void
   */
  public function setFreightClass($freightClass)
  {
    $this->freightClass = $freightClass; // should be in Constants::AVAILABLE_FREIGHT_CLASSES
  }

  /**
   * @return PalletDTO
   */
  public function getPalletInfo()
  {
    return $this->palletInfo;
  }

  /**
   * @param mixed $palletInfo
   *
   * @return void
   */
  public function setPalletInfo($palletInfo)
  {
    $this->palletInfo = PalletDTO::createFromArray($palletInfo);
  }

  /**
   * @return int
   */
  public function getGroupIdentifier()
  {
    return $this->groupIdentifier;
  }

  /**
   * @param mixed $groupIdentifier
   *
   * @return void
   */
  public function setGroupIdentifier($groupIdentifier)
  {
    $this->groupIdentifier = $groupIdentifier;
  }

  /**
   * @return int
   */
  public function getSequenceIdentifier()
  {
    return $this->sequenceIdentifier;
  }

  /**
   * @param mixed $sequenceIdentifier
   *
   * @return void
   */
  public function setSequenceIdentifier($sequenceIdentifier)
  {
    $this->sequenceIdentifier = $sequenceIdentifier;
  }

  /**
   * Static function to create a new Shipping Unit DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(ShippingUnitDTO::class);
    $dto->setPartNumber($params['partNumber'] ?? null);
    $dto->setUnitType($params['unitType'] ?? null);
    $dto->setWeight($params['weight'] ?? null);
    $dto->setDimensions($params['dimensions'] ?? null);
    $dto->setFreightClass($params['freightClass'] ?? null);
    $dto->setPalletInfo($params['palletInfo'] ?? null);
    $dto->setGroupIdentifier($params['groupIdentifier'] ?? null);
    $dto->setSequenceIdentifier($params['sequenceIdentifier'] ?? null);
    return $dto;
  }
}
