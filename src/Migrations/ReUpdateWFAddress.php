<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Services\AddressService;

/**
 *
 * Class UpdateWayfairAddress
 *
 * @package Wayfair\Migrations
 */
class ReUpdateWFAddress {
  /**
   * @var AddressService
   */
  private $addressService;

  /**
   * ReUpdateWFAddress constructor.
   *
   * @param AddressService $addressService
   */
  public function __construct(AddressService $addressService) {
    $this->addressService = $addressService;
  }

  public function run() {
    $this->addressService->checkAndUpdate();
  }
}
