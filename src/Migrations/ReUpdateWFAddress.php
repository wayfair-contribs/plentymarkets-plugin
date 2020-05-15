<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Plenty\Modules\Account\Contact\Contracts\ContactAddressRepositoryContract;
use Wayfair\Core\Dto\General\AddressDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\BillingAddress;
use Wayfair\Mappers\AddressMapper;
use Wayfair\Repositories\KeyValueRepository;
use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Wayfair\Services\AddressService;

/**
 *
 * Class UpdateWayfairAddress
 *
 * @package Wayfair\Migrations
 */
class ReUpdateWFAddress
{
  /**
   * @var AddressService
   */
  private $addressService;

  /**
   * ReUpdateWFAddress constructor.
   *
   * @param AddressService $addressService
   */
  public function __construct(AddressService $addressService)
  {
    $this->addressService = $addressService;
  }

  public function run()
  {
    $this->addressService->checkAndUpdate();
  }
}
