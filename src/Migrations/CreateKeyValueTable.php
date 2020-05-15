<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Wayfair\Models\KeyValue;

class CreateKeyValueTable {

  /**
   * @param Migrate $migrate
   *
   * @return void
   */
  public function run(Migrate $migrate)
  {
    $migrate->createTable(KeyValue::class);
  }
}
