<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

class InventoryFullCron extends AbstractInventoryCron
{

  /**
   * InventoryFullCron constructor.
   */
  public function __construct()
  {
    parent::__construct(true);
  }
}
