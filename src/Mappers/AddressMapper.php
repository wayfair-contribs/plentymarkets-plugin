<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Wayfair\Core\Dto\General\AddressDTO;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;

class AddressMapper
{
  /**
   * @var CountryRepositoryContract
   */
  public $countryRepositoryContract;

  /**
   * AddressMapper constructor.
   *
   * @param CountryRepositoryContract $countryRepositoryContract
   */
  public function __construct(CountryRepositoryContract $countryRepositoryContract)
  {
    $this->countryRepositoryContract = $countryRepositoryContract;
  }

  /**
   * @param AddressDTO $dto
   *
   * @return array
   */
  public function map(AddressDTO $dto) : array
  {
    $name = explode(' ', $dto->getName(), 2);
    $countryAndState = $this->getCountryAndState((string)$dto->getCountry(), (string)$dto->getState());
    $data = [
      'firstName' => $name[0] ?? '',
      'lastName' => $name[1] ?? '',
      'name1' => $dto->getName(),
      'name2' => $name[0] ?? '',
      'name3' => $name[1] ?? '',
      'address1' => $dto->getAddress1(),
      'address2' => $dto->getAddress2(),
      'town' => $dto->getCity(),
      'postalCode' => $dto->getPostalCode(),
      'countryId' => $countryAndState['countryId'],
      'stateId' => $countryAndState['stateId'],
      'phone' => $dto->getPhoneNumber(),
    ];
    return $data;
  }

  /**
   * @param string $country
   * @param string $state
   *
   * @return array
   */
  public function getCountryAndState(string $country, string $state): array
  {
    $country = $this->countryRepositoryContract->getCountryByIso($country, 'isoCode2');
    $state = $country->id ? $this->countryRepositoryContract->getCountryStateByIso($country->id, $state) : null;
    return ['countryId' => $country->id ?? null, 'stateId' => $state->id ?? null];
  }
}
