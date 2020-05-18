<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class AddressDTO {
  /**
   * @var string
   */
  protected $name;

  /**
   * @var string
   */
  protected $address1;

  /**
   * @var string
   */
  protected $address2;

  /**
   * @var string
   */
  protected $city;

  /**
   * @var string
   */
  protected $state;

  /**
   * @var string
   */
  protected $country;

  /**
   * @var string
   */
  protected $postalCode;

  /**
   * @var string
   */
  protected $phoneNumber;

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @param mixed $name
   *
   * @return void
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * @return string
   */
  public function getAddress1() {
    return $this->address1;
  }

  /**
   * @param mixed $address1
   *
   * @return void
   */
  public function setAddress1($address1) {
    $this->address1 = $address1;
  }

  /**
   * @return string|null
   */
  public function getAddress2() {
    return $this->address2;
  }

  /**
   * @param mixed $address2
   *
   * @return void
   */
  public function setAddress2($address2) {
    $this->address2 = $address2;
  }

  /**
   * @return string
   */
  public function getCity() {
    return $this->city;
  }

  /**
   * @param mixed $city
   *
   * @return void
   */
  public function setCity($city) {
    $this->city = $city;
  }

  /**
   * @return string|null
   */
  public function getState() {
    return $this->state;
  }

  /**
   * @param mixed $state
   *
   * @return void
   */
  public function setState($state) {
    $this->state = $state;
  }

  /**
   * @return string
   */
  public function getCountry() {
    return $this->country;
  }

  /**
   * @param mixed $country
   *
   * @return void
   */
  public function setCountry($country) {
    $this->country = $country;
  }

  /**
   * @return string|null
   */
  public function getPostalCode() {
    return $this->postalCode;
  }

  /**
   * @param mixed $postalCode
   *
   * @return void
   */
  public function setPostalCode($postalCode) {
    $this->postalCode = $postalCode;
  }

  /**
   * @return string|null
   */
  public function getPhoneNumber() {
    return $this->phoneNumber;
  }

  /**
   * @param mixed $phoneNumber
   *
   * @return void
   */
  public function setPhoneNumber($phoneNumber) {
    $this->phoneNumber = $phoneNumber;
  }

  /**
   * Static function to create a new Address DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self {
    $dto = pluginApp(AddressDTO::class);
    $dto->setName($params['name'] ?? null);
    $dto->setAddress1($params['address1'] ?? null);
    $dto->setAddress2($params['address2'] ?? null);
    $dto->setCity($params['city'] ?? null);
    $dto->setState($params['state'] ?? null);
    $dto->setCountry($params['country'] ?? null);
    $dto->setPostalCode($params['postalCode'] ?? null);
    $dto->setPhoneNumber($params['phoneNumber'] ?? null);
    return $dto;
  }
}
