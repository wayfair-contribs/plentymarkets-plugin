<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Wayfair\Models\OrderASN;

/**
 * Create Order ASN mapping table.
 * Class CreateOrderASNTable
 *
 * @package Wayfair\Migrations
 */
class CreateOrderASNTable
{
  /**
   * @param Migrate $migrate
   *
   * @return void
   */
  public function run(Migrate $migrate)
  {
    $migrate->createTable(OrderASN::class);
  }
}
