<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

class PendingLogs extends AbstractWayfairModel
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
