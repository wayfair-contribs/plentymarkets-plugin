<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Procedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Wayfair\Core\Api\Services\InventoryService;

class InventoryChangeProcedure {

  /**
   * @var InventoryService
   */
  private $inventoryService;

  /**
   * @param InventoryService $inventoryService
   */
  public function __construct(InventoryService $inventoryService) {
    $this->inventoryService = $inventoryService;
  }

  /**
   * @param EventProceduresTriggered $eventProceduresTriggered
   *
   * @return void
   */
  public function run(EventProceduresTriggered $eventProceduresTriggered) {
    // TODO: Need a way get inventory to be updated.
    $eventProceduresTriggered->getOrder();
  }
}