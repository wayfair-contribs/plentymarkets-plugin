<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class PalletDTO {
  /**
   * @var WeightDTO
   */
  private $weight;

  /**
   * @return WeightDTO
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * @param mixed $weight
   *
   * @return void
   */
  public function setWeight($weight) {
    $this->weight = WeightDTO::createFromArray($weight);
  }

  /**
   * Static function to create a new Pallet DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self {
    $dto = pluginApp(PalletDTO::class);
    $dto->setWeight($params['weight'] ?? null);
    return $dto;
  }
}
