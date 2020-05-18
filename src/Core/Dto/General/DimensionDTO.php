<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class DimensionDTO {
  /**
   * @var MeasurementDTO
   */
  private $length;

  /**
   * @var MeasurementDTO
   */
  private $width;

  /**
   * @var MeasurementDTO
   */
  private $height;

  /**
   * @return MeasurementDTO
   */
  public function getLength() {
    return $this->length;
  }

  /**
   * @param mixed $length
   *
   * @return void
   */
  public function setLength($length) {
    $this->length = MeasurementDTO::createFromArray($length);
  }

  /**
   * @return MeasurementDTO
   */
  public function getWidth() {
    return $this->width;
  }

  /**
   * @param mixed $width
   *
   * @return void
   */
  public function setWidth($width) {
    $this->width = MeasurementDTO::createFromArray($width);
  }

  /**
   * @return MeasurementDTO
   */
  public function getHeight() {
    return $this->height;
  }

  /**
   * @param mixed $height
   *
   * @return void
   */
  public function setHeight($height) {
    $this->height = MeasurementDTO::createFromArray($height);
  }

  /**
   * Static function to create a new Dimension DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self {
    $dto = pluginApp(DimensionDTO::class);
    $dto->setLength($params['length'] ?? null);
    $dto->setWidth($params['width'] ?? null);
    $dto->setHeight($params['height'] ?? null);
    return $dto;
  }
}
