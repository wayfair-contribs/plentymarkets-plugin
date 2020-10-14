<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class PendingOrders extends Model {

  /**
   * @var      int
   * @property int
   */
  public $id = 0;

  /**
   * @var      string
   * @property string
   */
  public $poNum = '';

  public $items = '';

  /**
   * @var      int
   * @property int
   */
  public $attempts = 0;

  /**
   * @return string
   */
  public function getTableName(): string {
    return 'Wayfair::PendingOrders';
  }

  /**
   * @var array
   */
  protected $textFields = ['items'];
}
