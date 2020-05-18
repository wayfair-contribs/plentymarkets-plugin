<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

use Wayfair\Http\WayfairResponse;

interface ClientInterfaceContract {

  /**
   * @param string $method
   * @param array  $arguments
   *
   * @return WayfairResponse
   */
  public function call($method, $arguments);
}