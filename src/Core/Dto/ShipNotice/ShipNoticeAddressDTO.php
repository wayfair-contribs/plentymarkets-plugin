<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\ShipNotice;

use Wayfair\Core\Dto\General\AddressDTO;

/**
 * Address object for ASN message.
 * Class AddressDTO
 *
 * @package Wayfair\Core\Dto\ShipNotice
 */
class ShipNoticeAddressDTO extends AddressDTO
{

  /**
   * @var string
   */
  private $streetAddress1;

  /**
   * @var string
   */
  private $streetAddress2;

  /**
   * @return string
   */
  public function getStreetAddress1()
  {
    return $this->streetAddress1;
  }

  /**
   * @param string $streetAddress1
   */
  public function setStreetAddress1($streetAddress1)
  {
    $this->streetAddress1 = $streetAddress1;
  }

  /**
   * @return string
   */
  public function getStreetAddress2()
  {
    return $this->streetAddress2;
  }

  /**
   * @param string $streetAddress2
   */
  public function setStreetAddress2($streetAddress2)
  {
    $this->streetAddress2 = $streetAddress2;
  }

  /**
   * Convert current object to associative array.
   *
   * @return array
   */
  public function toArray(): array
  {
    return [
      'name'           => $this->getName(),
      'streetAddress1' => $this->getStreetAddress1(),
      'streetAddress2' => $this->getStreetAddress2(),
      'city'           => $this->getCity(),
      'state'          => $this->getState(),
      'postalCode'     => $this->getPostalCode(),
      'country'        => $this->getCountry()
    ];
  }
}
