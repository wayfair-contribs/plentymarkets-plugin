<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Wayfair\Models\WarehouseSupplier;

class CreateWarehouseSupplierTable
{

  /**
   * @param Migrate $migrate
   *
   * @return null
   */
  public function run(Migrate $migrate)
  {
    $migrate->createTable(WarehouseSupplier::class);
  }
}
