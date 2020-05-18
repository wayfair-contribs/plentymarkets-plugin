<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;
use Wayfair\Models\PendingLogs;

class CreatePendingLogsTable {

  /**
   * @param Migrate $migrate
   *
   * @return void
   */
  public function run(Migrate $migrate) {
    $migrate->createTable(PendingLogs::class);
  }
}