<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class KeyValue extends Model {

  /**
   * @var string
   */
  protected $primaryKeyFieldName = 'key';

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
  public function getTableName(): string {
    return 'Wayfair::KeyValue';
  }

  /**
   * @var array
   */
  protected $textFields = ['value'];
}
