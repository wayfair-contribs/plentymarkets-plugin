<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Helpers;

class BillingAddress {
  const NAME = 'Wayfair Stores Ltd';
  const ADDRESS = 'Wayfair House, Tuam Road';
  const CITY = 'Galway';
  const POSTCODE = 'H91 W260';
  const COUNTRY = 'IE';
  const BillingAddressAsArray = [
    'name' => self::NAME,
    'address1' => self::ADDRESS,
    'city' => self::CITY,
    'postalCode' => self::POSTCODE,
    'country' => self::COUNTRY,
  ];
}
