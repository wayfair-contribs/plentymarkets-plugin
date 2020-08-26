<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Services\InventoryStatusService;

/**
 * Class ClearInventoryState
 *
 * Clear out state of inventory on deploy,
 * So that the system can calibrate and start syncing inventory immediately.
 *
 * @package Wayfair\Migrations
 */
class ClearInventoryState {
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
    $this->inventoryStatusService->clearState(false);
    $this->inventoryStatusService->clearState(true);
  }
}
