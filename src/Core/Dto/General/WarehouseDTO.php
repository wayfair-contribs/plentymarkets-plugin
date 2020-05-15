<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class WarehouseDTO
{
  /**
   * @var string
   */
  private $id;

  /**
   * @var string
   */
  private $name;

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param mixed $id
   *
   * @return void
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @param mixed $name
   *
   * @return void
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * Static function to create a new Warehouse DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(WarehouseDTO::class);
    $dto->setId($params['id'] ?? null);
    $dto->setName($params['name'] ?? null);
    return $dto;
  }
}
