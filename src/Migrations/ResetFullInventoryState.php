<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Services\FullInventoryStatusService;

/**
 * Class ResetFullInventoryState
 *
 * Make sure Full Inventory State is set to idle at boot
 *
 * @package Wayfair\Migrations
 */
class ResetFullInventoryState {
  /**
   * @var FullInventoryStatusService
   */
  private $fullInventoryStatusService;

  /**
   * ResetFullInventoryState constructor.
   *
   * @param AddressService $addressService
   */
  public function __construct(FullInventoryStatusService $fullInventoryStatusService) {
    $this->fullInventoryStatusService = $fullInventoryStatusService;
  }

  public function run() {
    $this->fullInventoryStatusService->markFullInventoryIdle();
  }
}
