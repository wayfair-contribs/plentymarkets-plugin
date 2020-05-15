<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface StorageInterfaceContract {

  /**
   * @param string $key
   *
   * @return mixed
   */
  public function get($key);

  /**
   * @param string $key
   * @param mixed  $value
   *
   * @return void
   */
  public function set($key, $value);
}
