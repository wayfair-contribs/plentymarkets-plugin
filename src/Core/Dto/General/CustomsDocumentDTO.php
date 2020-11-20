<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

/**
 * DTO for the "customsDocument" element in the Wayfair shipment registration schema
 */
class CustomsDocumentDTO
{
  /**
   * @var bool
   */
  private $required;

  /**
   * @var string
   */
  private $url;

  /**
   * @return bool
   */
  public function getRequired()
  {
    return isset($this->required) && $this->required;
  }

  /**
   * @param mixed $required
   *
   * @return void
   */
  public function setRequired($required)
  {
    $this->required = isset($required) && $required;
  }

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
   * Static function to create a new Generated Shipping Label DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    /** @var CustomsDocumentDTO */
    $dto = pluginApp(CustomsDocumentDTO::class);
    $dto->setRequired($params['required'] ?? null);
    $dto->setUrl($params['url'] ?? null);
    return $dto;
  }
}
