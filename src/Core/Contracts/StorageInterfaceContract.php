<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface StorageInterfaceContract {

  /**
   * Get the model stored for the key
   * @param string $key
   *
   * @return mixed
   */
  public function get($key);

  /**
   * Set the model for the key
   * @param string $key
   * @param mixed  $value
   *
   * @return void
   */
  public function set($key, $value);

  /**
   * Remove the model for the key
   *
   * @param string $key
   * @return mixed
   */
  public function remove($key);
}