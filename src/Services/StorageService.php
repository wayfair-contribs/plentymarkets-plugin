<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Repositories\KeyValueRepository;

class StorageService implements StorageInterfaceContract {

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * StorageService constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository) {
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * Get the value for the key
   * 
   * @param string $key
   *
   * @return mixed
   */
  public function get($key) {
    return $this->keyValueRepository->get($key);
  }

  /**
   * Set the value for the key
   * 
   * @param string $key
   * @param mixed  $value
   *
   * @throws \Plenty\Exceptions\ValidationException
   * @return void
   */
  public function set($key, $value) {
    $this->keyValueRepository->put($key, $value);
  }

  /**
   * Remove the value for the key,
   * returning the value if stored
   *
   * @param string $key
   * @return mixed
   */
  public function remove($key)
  {
    return $this->keyValueRepository->delete($key);
  }

}