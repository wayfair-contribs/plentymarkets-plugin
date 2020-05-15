<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Helpers;

class TimeHelper {

  /**
   * @return int
   */
  public static function getMilliseconds(): int
  {
    return round(microtime(true) * 1000, 0);
  }
}
