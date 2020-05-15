<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\Inventory;

class ErrorDTO
{
  /**
   * @var string
   */
  private $key;

  /**
   * @return string
   */
  public function getKey()
  {
    return $this->key;
  }

  /**
   * @param mixed $key
   *
   * @return void
   */
  public function setKey($key)
  {
    $this->key = $key;
  }

  /**
   * Static function to create a new Error DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(ErrorDTO::class);
    $dto->setKey($params['key'] ?? null);
    return $dto;
  }

  /**
   * @return array
   */
  public function toArray()
  {
    $data = [];
    $data['key'] = $this->getKey();
    return $data;
  }
}
