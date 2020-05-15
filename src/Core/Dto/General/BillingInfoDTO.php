<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class BillingInfoDTO {
  /**
   * @var string
   */
  private $vatNumber;

  /**
   * @return string
   */
  public function getVatNumber()
  {
    return $this->vatNumber;
  }

  /**
   * @param mixed $vatNumber
   *
   * @return void
   */
  public function setVatNumber($vatNumber)
  {
    $this->vatNumber = $vatNumber;
  }

  /**
   * Static function to create a new Billing Info DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(BillingInfoDTO::class);
    $dto->setVatNumber($params['vatNumber'] ?? null);
    return $dto;
  }
}
