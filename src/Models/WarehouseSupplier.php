<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class WarehouseSupplier extends Model
{

  /**
   * @var      int
   * @property int
   */
  public $id = 0;

  /**
   * @var      string
   * @property string
   */
  public $supplierId = '';

  /**
   * @var      string
   * @property string
   */
  public $warehouseId = '';

  /**
   * @var      int
   * @property int
   */
  public $createdAt = 0;

  /**
   * @return string
   */
  public function getTableName(): string
  {
    return 'Wayfair::WarehouseSupplier';
  }

  public function attributesToArray(): array
  {
    return parent::attributesToArray();
  }

  public function getAttribute(string $key)
  {
    return parent::getAttribute($key);
  }

  public function getAttributeValue(string $key)
  {
    return parent::getAttributeValue($key);
  }

  public function hasGetMutator(string $key): bool
  {
    return parent::hasGetMutator($key);
  }

  public function setAttribute(string $key, $value): Model
  {
    parent::setAttribute($key, $value);
    return $this;
  }

  public function hasSetMutator(string $key): bool
  {
    return parent::hasSetMutator($key);
  }

  public function fillJsonAttribute(string $key, $value): Model
  {
    parent::fillJsonAttribute($key, $value);
    return $this;
  }

  public function fromJson(string $value, bool $asObject = false)
  {
    return parent::fromJson($value, $asObject);
  }

  public function fromDateTime($value): string
  {
    return parent::fromDateTime($value);
  }

  public function getDates(): array
  {
    return parent::getDates();
  }

  public function setDateFormat(string $format): Model
  {
    parent::setDateFormat($format);
    return $this;
  }

  public function hasCast(string $key, $types = null): bool
  {
    return parent::hasCast($key, $types);
  }

  public function getCasts(): array
  {
    return parent::getCasts();
  }

  public function getAttributes(): array
  {
    return parent::getAttributes();
  }

  public function setRawAttributes(array $attributes, bool $sync = false): Model
  {

    parent::setRawAttributes($attributes, $sync);
    return $this;
  }

  public function getOriginal(string $key = null, $default = null)
  {
    return parent::getOriginal($key, $default);
  }

  public function only($attributes): array
  {
    return parent::only($attributes);
  }

  public function syncOriginal(): Model
  {
    parent::syncOriginal();
    return $this;
  }

  public function syncOriginalAttribute(string $attribute): Model
  {
    parent::syncOriginalAttribute($attribute);
    return $this;
  }

  public function syncChanges(): Model
  {
    parent::syncChanges();
    return $this;
  }

  public function isDirty($attributes = null): bool
  {
    return parent::isDirty($attributes);
  }

  public function isClean($attributes = null): bool
  {
    return parent::isClean($attributes);
  }

  public function wasChanged($attributes = null): bool
  {
    return parent::wasChanged($attributes);
  }

  public function getDirty(): array
  {
    return parent::getDirty();
  }

  public function getChanges(): array
  {
    return parent::getChanges();
  }

  public function getMutatedAttributes(): array
  {
    return parent::getMutatedAttributes();
  }

  public static function cacheMutatedAttributes(string $class)
  {
    return parent::cacheMutatedAttributes($class);
  }

  public function relationLoaded()
  {
    return parent::relationLoaded();
  }
}
