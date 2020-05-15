<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Plugin\Http\Request;
use Wayfair\Services\ShipmentProviderService;

/**
 * Carrier SCAC code mapping controller.
 * Class CarrierScacController
 *
 * @package Wayfair\Controllers
 */
class CarrierScacController {

  const INPUT_DATA = 'data';

  /**
   * @var ShipmentProviderService
   */
  private $shipmentProviderService;

  /**
   * CarrierScacController constructor.
   *
   * @param ShipmentProviderService $shipmentProviderService
   */
  public function __construct(ShipmentProviderService $shipmentProviderService)
  {
    $this->shipmentProviderService = $shipmentProviderService;
  }

  /**
   * Get list of carriers using PlentyMarket Shipping Providers.
   *
   * @return mixed
   */
  public function getCarriers()
  {
    return json_encode($this->shipmentProviderService->getShippingProviders());
  }

  /**
   * Get current mapping setting store in key-value database.
   *
   * @return mixed
   */
  public function getMapping()
  {
    return json_encode($this->shipmentProviderService->getCarrierScacMapping());
  }

  /**
   * @param Request $request
   *
   * @return false|string
   */
  public function post(Request $request)
  {
    $input = $request->get(self::INPUT_DATA);

    return json_encode($this->shipmentProviderService->saveCarrierScacMapping($input));
  }

  /**
   * Get shipping method specify for supplier.
   *
   * @return false|string
   */
  public function getShippingMethod()
  {
    return json_encode(['name' => $this->shipmentProviderService->getShippingMethod()]);
  }

  /**
   * Update shipping method that supplier uses products.
   *
   * @param Request $request
   *
   * @return false|string
   * @throws ValidationException
   */
  public function postShippingMethod(Request $request)
  {
    $input = $request->get(self::INPUT_DATA);

    return json_encode(['name' => $this->shipmentProviderService->updateShippingMethod($input)]);
  }

}
