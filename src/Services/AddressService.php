<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;


use Plenty\Modules\Account\Address\Contracts\AddressContactRelationRepositoryContract;
use Plenty\Modules\Account\Address\Models\AddressOption;
use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Plenty\Modules\Account\Contact\Contracts\ContactAddressRepositoryContract;
use Plenty\Modules\Account\Contact\Contracts\ContactRepositoryContract;
use Wayfair\Core\Dto\General\AddressDTO;
use Wayfair\Core\Dto\General\BillingInfoDTO;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Core\Helpers\BillingAddress;
use Wayfair\Mappers\AddressMapper;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Class UpdateAddressService
 *
 * @package Wayfair\Services
 */
class AddressService {
  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;
  /**
   * @var ContactAddressRepositoryContract
   */
  private $contactAddressRepository;
  /**
   * @var AddressMapper
   */
  private $addressMapper;

  /**
   * AddressService constructor.
   *
   * @param KeyValueRepository               $keyValueRepository
   * @param ContactAddressRepositoryContract $contactAddressRepository
   * @param AddressMapper                    $addressMapper
   */
  public function __construct(
      KeyValueRepository $keyValueRepository,
      ContactAddressRepositoryContract $contactAddressRepository,
      AddressMapper $addressMapper
  ) {
    $this->keyValueRepository = $keyValueRepository;
    $this->contactAddressRepository = $contactAddressRepository;
    $this->addressMapper = $addressMapper;
  }

  /**
   * Update existing Wayfair address using ContactAddressRepositoryContract.
   */
  public function checkAndUpdate() {
    $billingSetting = \json_decode($this->keyValueRepository->get(ConfigHelperContract::BILLING_CONTACT), true);
    if (!empty($billingSetting['addressId']) && !empty($billingSetting['contactId'])) { // Billing address already exist -> update it.
      $addressDTO = AddressDTO::createFromArray(BillingAddress::BillingAddressAsArray);
      $addressData = $this->addressMapper->map($addressDTO);
      $this->contactAddressRepository->updateAddress($addressData, $billingSetting['addressId'], $billingSetting['contactId'], AddressRelationType::BILLING_ADDRESS);
    }
  }

  /**
   * @param AddressDTO     $dto
   * @param BillingInfoDTO $billingInfoDto
   * @param int            $referrerId
   * @param int            $contactType
   * @param int            $addressRelationType
   *
   * @return array
   */
  public function createContactAndAddress(AddressDTO $dto, BillingInfoDTO $billingInfoDto, int $referrerId, int $contactType, int $addressRelationType): array {
    $addressData = $this->addressMapper->map($dto);
    $contactId = $this->createContact($addressData, $referrerId, $contactType);
    $addressId = $this->createAddress($addressData, $contactId, $addressRelationType, $billingInfoDto);

    return ['contactId' => $contactId, 'addressId' => $addressId];
  }

  /**
   * @param array $address
   * @param int   $referrerId
   * @param int   $typeId
   *
   * @return int
   */
  public function createContact(array $address, int $referrerId, int $typeId): int {
    $address['typeId'] = $typeId;
    $address['referrerId'] = $referrerId;
    $contactRepo = pluginApp(ContactRepositoryContract::class);
    $contact = $contactRepo->createContact($address);

    return $contact->id;
  }

  /**
   * @param array          $address
   * @param int            $contactId
   * @param int            $typeId
   * @param BillingInfoDTO $billingInfoDto
   *
   * @return int
   */
  public function createAddress(array $address, int $contactId, int $typeId, BillingInfoDTO $billingInfoDto): int {
    if ($typeId === AddressRelationType::DELIVERY_ADDRESS) {
      $address['options'] = [
          [
              'typeId' => AddressOption::TYPE_VAT_NUMBER,
              'value' => $billingInfoDto->getVatNumber(),
          ]
      ];
    }
    /** @var ContactAddressRepositoryContract $contactAddressRepo */
    $contactAddressRepo = pluginApp(ContactAddressRepositoryContract::class);
    $address = $contactAddressRepo->createAddress($address, $contactId, $typeId);
    $addressContactRelationRepo = pluginApp(AddressContactRelationRepositoryContract::class);
    $addressContactRelationRepo->createAddressContactRelation(
        [
            [
                'contactId' => $contactId,
                'addressId' => $address->id,
                'typeId' => $typeId,
            ]
        ]
    );

    return $address->id;
  }
}