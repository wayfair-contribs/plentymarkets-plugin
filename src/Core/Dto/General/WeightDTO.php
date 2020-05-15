<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class WeightDTO {
  /**
   * @var float
   */
  private $value;

  /**
   * @var string
   */
  private $unit;

  /**
   * @return float
   */
  public function getValue()
  {
    return $this->value;
  }

  /**
   * @param mixed $value
   *
   * @return void
   */
  public function setValue($value)
  {
    $this->value = $value;
  }

  /**
   * @return string
   */
  public function getUnit()
  {
    return $this->unit;
  }

  /**
   * @param mixed $unit
   *
   * @return void
   */
  public function setUnit($unit)
  {
    $this->unit = $unit; // should be in Constants::AVAILABLE_WEIGHT_UNITS
  }

  /**
   * Static function to create a new Weight DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(WeightDTO::class);
    $dto->setValue($params['value'] ?? null);
    $dto->setUnit($params['unit'] ?? null);
    return $dto;
  }
}
