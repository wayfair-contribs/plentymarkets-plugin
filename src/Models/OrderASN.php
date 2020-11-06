<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

/**
 * Class OrderASN
 *
 * @package Wayfair\Models
 */
class OrderASN extends AbstractWayfairModel
{
  /**
   * @var      int
   * @property int
   */
  public $id = 0;

  /**
   * @var      int
   * @property int
   */
  public $orderId;

  /**
   * @var      string
   * @property string
   */
  public $type;

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
    return 'Wayfair::OrderASN';
  }
}
