<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Services\FullInventoryStatusService;

class ResetFullInventoryStatus {

  /**
   * @var FullInventoryStatusService
   */
  private $fullInventoryStatusService;

  /**
   * @param FullInventoryStatusService $fullInventoryStatusService
   */
  public function __construct(FullInventoryStatusService $fullInventoryStatusService) {
    $this->fullInventoryStatusService = $fullInventoryStatusService;
  }

  public function run() {
    $this->fullInventoryStatusService->markFullInventoryIdle();
  }
}
