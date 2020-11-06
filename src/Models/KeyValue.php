<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

class KeyValue extends AbstractWayfairModel
{

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
  public function getTableName(): string
  {
    return 'Wayfair::KeyValue';
  }

  /**
   * @var array
   */
  protected $textFields = ['value'];
}
