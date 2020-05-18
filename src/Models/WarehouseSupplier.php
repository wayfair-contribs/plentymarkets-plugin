<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class WarehouseSupplier extends Model {

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
  public function getTableName(): string {
    return 'Wayfair::WarehouseSupplier';
  }
}