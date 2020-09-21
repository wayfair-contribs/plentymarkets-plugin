<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Plenty\Modules\Order\Models\OrderType;
use Plenty\Plugin\Application;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Repositories\KeyValueRepository;

class PurchaseOrderMapper {
  const ORDER_STATUS_WAITING_FOR_ACTIVATION = 2;
  const ORDER_STATUS_CANCELED = 8;
  /**
   * @var Application
   */
  public $app;

  /**
   * @var ProductMapper
   */
  public $productMapper;

  /**
   * @var KeyValueRepository
   */
  public $keyValueRepository;

  /**
   * PurchaseOrderMapper constructor.
   *
   * @param Application        $app
   * @param ProductMapper      $productMapper
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(Application $app, ProductMapper $productMapper, KeyValueRepository $keyValueRepository) {
    $this->app = $app;
    $this->productMapper = $productMapper;
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * @param ResponseDTO $dto
   * @param int         $billingAddressId
   * @param int         $billingContactId
   * @param int         $deliveryAddressId
   * @param float         $referrerId
   * @param string      $warehouseId
   * @param string      $paymentMethodId
   *
   * @return array
   */
  public function map(ResponseDTO $dto, int $billingAddressId, int $billingContactId, int $deliveryAddressId, float $referrerId, string $warehouseId, string $paymentMethodId): array {

    /** @var AbstractConfigHelper */
    $configHelper = pluginApp(AbstractConfigHelper::class);
    $itemMappingMethod = $configHelper->getItemMappingMethod();

    $orderItems = [];

    foreach ($dto->getProducts() as $product) {
      $orderItems[] = $this->productMapper->map($product, $referrerId, $warehouseId, $dto->getPoNumber(), $itemMappingMethod);
    }
    // Init properties
    $properties = [
      [
        'typeId' => OrderPropertyType::PAYMENT_METHOD,
        'value' => $paymentMethodId
      ],
      [
        'typeId' => OrderPropertyType::EXTERNAL_ORDER_ID,
        'value' => $dto->getPoNumber()
      ]
    ];
    if ($warehouseId) {
      $properties[] = [
        'typeId' => OrderPropertyType::WAREHOUSE,
        'value' => $warehouseId
      ];
    }
    // Init address relations
    $addressRelations = [
      [
        'typeId' => AddressRelationType::BILLING_ADDRESS,
        'addressId' => $billingAddressId,
      ],
      [
        'typeId' => AddressRelationType::DELIVERY_ADDRESS,
        'addressId' => $deliveryAddressId,
      ]
    ];
    // Init relations
    $relations = [
      [
        'referenceType' => 'contact',
        'referenceId' => $billingContactId,
        'relation' => 'receiver',
      ]
    ];
    $data = [
      'typeId' => OrderType::TYPE_SALES_ORDER,
      'referrerId' => $referrerId,
      'plentyId' => $this->app->getPlentyId(),
      'orderItems' => $orderItems,
      'statusId' => (float)$this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY) ?? self::ORDER_STATUS_WAITING_FOR_ACTIVATION,
      'properties' => $properties,
      'addressRelations' => $addressRelations,
      'relations' => $relations
    ];
    return $data;
  }
}
