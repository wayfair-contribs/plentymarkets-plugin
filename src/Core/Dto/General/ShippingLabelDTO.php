<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class ShippingLabelDTO {
  /**
   * @var string
   */
  private $url;

  /**
   * @return string
   */
  public function getUrl()
  {
    return $this->url;
  }

  /**
   * @param mixed $url
   *
   * @return void
   */
  public function setUrl($url)
  {
    $this->url = $url;
  }

  /**
   * Static function to create a new Shipping Label DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(ShippingLabelDTO::class);
    $dto->setUrl($params['url'] ?? null);
    return $dto;
  }
}
