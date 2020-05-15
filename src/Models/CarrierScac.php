<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * Class CarrierScac
 *
 * @package Wayfair\Models
 */
class CarrierScac extends Model
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
  public $carrierId = '';

  /**
   * @var      string
   * @property string
   */
  public $scac = '';

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
    return 'Wayfair::CarrierScac';
  }
}
