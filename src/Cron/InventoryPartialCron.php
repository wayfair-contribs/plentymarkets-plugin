<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

class InventoryPartialCron extends AbstractInventoryCron
{

  /**
   * InventoryPartialCron constructor.
   */
  public function __construct()
  {
    parent::__construct(false);
  }
}