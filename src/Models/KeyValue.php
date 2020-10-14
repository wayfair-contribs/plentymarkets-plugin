<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class KeyValue extends Model
{
  const FIELD_NAME_KEY = 'key';

  const FIELD_NAME_VALUE = 'value';

  /**
   * @var string
   */
  protected $primaryKeyFieldName = KeyValue::FIELD_NAME_KEY;

  /**
   * @var string
   */
  protected $primaryKeyFieldType = 'string';

  /**
   * @var bool
   */
  protected $autoIncrementPrimaryKey = false;

  /**
   * @var      string
   * @property string
   */
  public $key = '';

  public $value = '';

  /**
   * @return string
   */
  public function getTableName(): string
  {
    return 'Wayfair::KeyValue';
  }

  /**
   * @var array
   */
  protected $textFields = [KeyValue::FIELD_NAME_VALUE];

  /**
   * @return array
   */
  public function toArray()
  {
    $result = [];
    $result[self::FIELD_NAME_KEY] = $this->key;
    $result[self::FIELD_NAME_VALUE] = $this->value;
  }

  /**
   * Adopt the array's data into this Model
   *
   * @param array $params Params
   *
   * @return void
   */
  public function adoptArray(array $params)
  {
    $this->key = $params[self::FIELD_NAME_KEY] ?? '';
    $this->key = $params[self::FIELD_NAME_VALUE] ?? null;
  }
}
