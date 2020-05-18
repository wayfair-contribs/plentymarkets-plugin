<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Data;

interface KeyValueStore {

  /**
   * @param string $key
   * @param mixed  $value
   *
   * @return void
   */
  public function set($key, $value);

  /**
   * @param string $key
   *
   * @return mixed
   */
  public function get($key);
}