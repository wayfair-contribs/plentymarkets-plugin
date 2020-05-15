<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Order\Shipping\ServiceProvider\Contracts\ShippingServiceProviderRepositoryContract;
use Plenty\Modules\Order\Status\Contracts\OrderStatusRepositoryContract;
use Wayfair\Core\Dto\Constants;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Models\CarrierScac;
use Wayfair\Repositories\CarrierScacRepository;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Class ShipmentProviderService
 *
 * @package Wayfair\Services
 */
class ShipmentProviderService {
  /**
   * @var ShippingServiceProviderRepositoryContract
   */
  private $shippingServiceProviderRepositoryContract;

  /**
   * @var OrderStatusRepositoryContract
   */
  private $orderStatusRepositoryContract;

  /**
   * @var CarrierScacRepository
   */
  private $carrierScacRepository;

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  public function __construct(
    ShippingServiceProviderRepositoryContract $shippingServiceProviderRepositoryContract,
    OrderStatusRepositoryContract $orderStatusRepositoryContract,
    CarrierScacRepository $carrierScacRepository,
    KeyValueRepository $keyValueRepository
  ) {
    $this->shippingServiceProviderRepositoryContract = $shippingServiceProviderRepositoryContract;
    $this->carrierScacRepository                     = $carrierScacRepository;
    $this->keyValueRepository                        = $keyValueRepository;
    $this->orderStatusRepositoryContract             = $orderStatusRepositoryContract;
  }

  /**
   * Update shipping method.
   *
   * @param string $data
   *
   * @return string
   * @throws ValidationException
   */
  public function updateShippingMethod(string $data)
  {
    $this->keyValueRepository->putOrReplace(AbstractConfigHelper::SHIPPING_METHOD, $data);

    return $data;
  }

  /**
   * Get shipping method setting
   *
   * @return string
   */
  public function getShippingMethod()
  {
    return $this->keyValueRepository->get(AbstractConfigHelper::SHIPPING_METHOD);
  }

  /**
   * Check if supplier shipping with Wayfair or not.
   *
   * @return bool
   */
  public function isShippingWithWayfair(): bool
  {
    $shippingMethod = $this->keyValueRepository->get(AbstractConfigHelper::SHIPPING_METHOD);

    return ($shippingMethod === Constants::SHIPPING_WITH_WF);
  }

  /**
   * Get a list of shipping service providers.
   *
   * @return array
   */
  public function getShippingProviders()
  {
    return $this->shippingServiceProviderRepositoryContract->all(['*']);
  }

  /**
   * Get Carrier scac mapping.
   *
   * @return array
   */
  public function getCarrierScacMapping(): array
  {
    $formatted = [];
    $models    = $this->carrierScacRepository->findAll();
    /** @var CarrierScac $model */
    foreach ($models as $model) {
      $formatted[] = ['carrierId' => $model->carrierId, 'scac' => $model->scac];
    }

    return $formatted;
  }

  /**
   * Save carrier scac mapping data to supplier PM database.
   *
   * @param array $data
   *
   * @return array
   */
  public function saveCarrierScacMapping(array $data): array
  {
    if (!empty($data) && is_array($data)) {
      $this->carrierScacRepository->save($data);
    }

    return $this->getCarrierScacMapping();
  }

}
