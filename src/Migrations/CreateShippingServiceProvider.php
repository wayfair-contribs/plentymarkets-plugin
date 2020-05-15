<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Plenty\Modules\Order\Shipping\ServiceProvider\Contracts\ShippingServiceProviderRepositoryContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Migration to create shipping service provider.
 * Class CreateShippingServiceProvider
 *
 * @package Wayfair\Migrations
 */
class CreateShippingServiceProvider {

  /**
   * @var ShippingServiceProviderRepositoryContract
   */
  private $shippingServiceProviderRepository;

  /**
   * CreateShippingServiceProvider constructor.
   *
   * @param ShippingServiceProviderRepositoryContract $shippingServiceProviderRepository
   */
  public function __construct(
    ShippingServiceProviderRepositoryContract $shippingServiceProviderRepository
  ) {
    $this->shippingServiceProviderRepository = $shippingServiceProviderRepository;
  }

  /**
   * @param KeyValueRepository $keyValueRepository
   *
   * @return void
   */
  public function run(KeyValueRepository $keyValueRepository)
  {
    try {
      $shippingServiceProvider = $this->shippingServiceProviderRepository->saveShippingServiceProvider(
          AbstractConfigHelper::PLUGIN_NAME,
          AbstractConfigHelper::SHIPPING_PROVIDER_NAME
      );

      $keyValueRepository->putOrReplace(AbstractConfigHelper::SHIPPING_PROVIDER_ID, $shippingServiceProvider->id);
    } catch (\Exception $exception) {
    }
  }
}
