<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Services\InventoryStatusService;

/**
 * Class ResetFullInventoryState
 *
 * Make sure Full Inventory State is set to idle at boot
 *
 * @package Wayfair\Migrations
 */
class ResetFullInventoryState {
  /**
   * @var InventoryStatusService
   */
  private $inventoryStatusService;

  /**
   * ResetFullInventoryState constructor.
   *
   * @param AddressService $addressService
   */
  public function __construct(InventoryStatusService $inventoryStatusService) {
    $this->inventoryStatusService = $inventoryStatusService;
  }

  public function run() {
    $this->inventoryStatusService->resetState(true);
  }
}
