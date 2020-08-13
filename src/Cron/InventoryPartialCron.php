<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

class InventoryPartialCron extends InventoryCron
{

  /**
   * InventoryPartialCron constructor.
   *
   * @param ScheduledInventorySyncService $service
   */
  public function __construct()
  {
    parent::__construct(false);
  }
}
