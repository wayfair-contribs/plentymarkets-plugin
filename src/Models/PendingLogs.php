<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

class PendingLogs extends Model
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
  public $message = '';

  /**
   * @var      string
   * @property string
   */
  public $level = '';

  /**
   * @var      string
   * @property string
   */
  public $logType = '';

  public $metrics = '';

  public $details = '';

  /**
   * @return string
   */
  public function getTableName(): string
  {
    return 'Wayfair::PendingLogs';
  }

  /**
   * @var array
   */
  protected $textFields = ['details', 'metrics'];
}
