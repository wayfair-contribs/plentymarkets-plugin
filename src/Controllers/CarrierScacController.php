<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Plugin\Http\Request;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Services\ShipmentProviderService;
use Wayfair\Helpers\TranslationHelper;

/**
 * Carrier SCAC code mapping controller.
 * Class CarrierScacController
 *
 * @package Wayfair\Controllers
 */
class CarrierScacController {
  const LOG_KEY_CONTROLLER_IN = "controllerInput";
  const LOG_KEY_CONTROLLER_OUT = "controllerOutput";

  const INPUT_DATA = 'data';

  /**
   * @var ShipmentProviderService
   */
  private $shipmentProviderService;

    /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * CarrierScacController constructor.
   *
   * @param ShipmentProviderService $shipmentProviderService
   * @param LoggerContract $logger
   */
  public function __construct(ShipmentProviderService $shipmentProviderService, LoggerContract $logger) {
    $this->shipmentProviderService = $shipmentProviderService;
    $this->logger = $logger;
  }

  /**
   * Get list of carriers using PlentyMarket Shipping Providers.
   *
   * @return mixed
   */
  public function getCarriers() {

    $carrierData = json_encode($this->shipmentProviderService->getShippingProviders());

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $carrierData],
      'method'         => __METHOD__
    ]);

    return $carrierData;
  }

  /**
   * Get current mapping setting store in key-value database.
   *
   * @return mixed
   */
  public function getMapping() {

    $mappingData = json_encode($this->shipmentProviderService->getCarrierScacMapping());

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $mappingData],
      'method'         => __METHOD__
    ]);

    return $mappingData;
  }

  /**
   * @param Request $request
   *
   * @return false|string
   */
  public function post(Request $request) {
    $input = $request->get(self::INPUT_DATA);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_IN), [
      'additionalInfo' => ['payloadIn' => json_encode($input)],
      'method'         => __METHOD__
    ]);

    $dataOut = json_encode($this->shipmentProviderService->saveCarrierScacMapping($input));

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $dataOut],
      'method'         => __METHOD__
    ]);

    return $dataOut;
  }

  /**
   * Get shipping method specify for supplier.
   *
   * @return false|string
   */
  public function getShippingMethod() {
    $dataOut = json_encode(['name' => $this->shipmentProviderService->getShippingMethod()]);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $dataOut],
      'method'         => __METHOD__
    ]);

    return $dataOut;
  }

  /**
   * Update shipping method that supplier uses products.
   *
   * @param Request $request
   *
   * @return false|string
   * @throws ValidationException
   */
  public function postShippingMethod(Request $request) {
    $input = $request->get(self::INPUT_DATA);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_IN), [
      'additionalInfo' => ['payloadIn' => json_encode($input)],
      'method'         => __METHOD__
    ]);

    $dataOut = json_encode(['name' => $this->shipmentProviderService->updateShippingMethod($input)]);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $dataOut],
      'method'         => __METHOD__
    ]);

    return $dataOut;
  }

}
