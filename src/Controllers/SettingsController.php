<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

<<<<<<< HEAD
/**
 * Controller for the "Settings" tab in the Wayfair plugin
 */
class SettingsController
{
  const LOG_KEY_CONTROLLER_IN = "controllerInput";
  const LOG_KEY_CONTROLLER_OUT = "controllerOutput";
  const LOG_KEY_SETTING_MAPPING_METHOD = "settingMappingMethod";
=======
class SettingsController
{
>>>>>>> origin/master

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * SettingsController constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   * @param LoggerContract $logger
   */
<<<<<<< HEAD
  public function __construct(KeyValueRepository $keyValueRepository, LoggerContract $logger)
=======
  public function __construct(KeyValueRepository $keyValueRepository)
>>>>>>> origin/master
  {
    $this->keyValueRepository = $keyValueRepository;
    $this->logger = $logger;
  }

  /**
   * Get current values for settings on Settings tab
   *
   * @return string
   */
  public function get()
  {
    $stockBuffer = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY);
    $defaultOrderStatus = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY);
    $defaultShippingProvider = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY);
    $defaultItemMappingMethod = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);
    $orderImportDate = $this->keyValueRepository->get(AbstractConfigHelper::IMPORT_ORDER_SINCE);
    $isAllInventorySyncEnabled = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY);

    $data = [
      AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY => (empty($stockBuffer) ? 0 : $stockBuffer),
      AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY => (empty($defaultOrderStatus) ? null : $defaultOrderStatus),
      AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY => (empty($defaultShippingProvider) ? null : $defaultShippingProvider),
      AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD => (empty($defaultItemMappingMethod) ? AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER : $defaultItemMappingMethod),
      AbstractConfigHelper::IMPORT_ORDER_SINCE => (empty($orderImportDate) ? '' : $orderImportDate),
      AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY => (empty($isAllInventorySyncEnabled) ? false : $isAllInventorySyncEnabled),
    ];

    $payload = json_encode($data);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $payload],
      'method'         => __METHOD__
    ]);
    return $payload;
  }

  /**
   * Save values on Settings tab to storage
   *
   * @param Request  $request
   * @param Response $response
   *
   * @return false|string
   */
  public function post(Request $request, Response $response)
  {
    $data = $request->input('data');

<<<<<<< HEAD
    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_IN), [
      'additionalInfo' => ['payloadIn' => json_encode($data)],
      'method'         => __METHOD__
    ]);
=======
    if (!isset($data) || empty($data)) {
      return $response->json(['error' => 'No settings data provided'], Response::HTTP_BAD_REQUEST);
    }
>>>>>>> origin/master

    if (!is_numeric($data[AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY])) {
      return $response->json(['error' => 'Stock Buffer must be a number'], Response::HTTP_BAD_REQUEST);
    }

    // Default Shipping Provider is deprecated in versions 1.1.2 and up
    $inputDefaultShippingProvider = $data[AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY];
    if (isset($inputDefaultShippingProvider)) {
      if (!is_numeric($data[AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY])) {
        return $response->json(['error' => 'Shipping Provider ID must be a number'], Response::HTTP_BAD_REQUEST);
      }

      $inputDefaultShippingProvider = (int) $inputDefaultShippingProvider;
    }

    if (!is_numeric($data[AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY])) {
      return $response->json(['error' => 'Order Status ID must be be a number'], Response::HTTP_BAD_REQUEST);
    }

    if (
      empty($data[AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD])
      || !in_array(
        $data[AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD],
        [
          AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
          AbstractConfigHelper::ITEM_MAPPING_EAN,
          AbstractConfigHelper::ITEM_MAPPING_SKU
        ]
      )
    ) {
      return $response->json(['error' => 'Item mapping must be from the selection only'], Response::HTTP_BAD_REQUEST);
    }

    if (isset($data[AbstractConfigHelper::IMPORT_ORDER_SINCE])) {
      $date = \DateTime::createFromFormat('Y-m-d', $data[AbstractConfigHelper::IMPORT_ORDER_SINCE]);
      if (!$date || $date->format('Y-m-d') != $data[AbstractConfigHelper::IMPORT_ORDER_SINCE]) {
        return $response->json(['error' => 'Import orders since: value is incorrect'], Response::HTTP_BAD_REQUEST);
      }
    }

    $inputStockBuffer = (int) $data[AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY];
<<<<<<< HEAD
    $inputDefaultShippingProvider = (int) $data[AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY];
=======
>>>>>>> origin/master
    $inputDefaultOrderStatus = (int) $data[AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY];
    $inputDefaultItemMapping = $data[AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD];
    $importOrderSince = $data[AbstractConfigHelper::IMPORT_ORDER_SINCE];
    $isAllInventorySyncEnabled = isset($data[AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY]) && $data[AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY];

    if ($inputStockBuffer < 0) {
      return $response->json(['error' => 'Stock Buffer cannot be negative'], Response::HTTP_BAD_REQUEST);
    }

    try {
      $this->keyValueRepository->putOrReplace(AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY, $inputStockBuffer);
      $this->keyValueRepository->putOrReplace(AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY, $inputDefaultShippingProvider);
      $this->keyValueRepository->putOrReplace(AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY, $inputDefaultOrderStatus);

      $this->logger->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_SETTING_MAPPING_METHOD),
        [
          'additionalInfo' => ['mappingMethod' => $inputDefaultItemMapping,],
          'method' => __METHOD__
        ]
      );

      $this->keyValueRepository->putOrReplace(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD, $inputDefaultItemMapping);
      $this->keyValueRepository->putOrReplace(AbstractConfigHelper::IMPORT_ORDER_SINCE, $importOrderSince);
      $this->keyValueRepository->putOrReplace(AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY, $isAllInventorySyncEnabled);

<<<<<<< HEAD
      $dataOut = [
        AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY => $inputStockBuffer,
        AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY => $inputDefaultShippingProvider,
        AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY => $inputDefaultOrderStatus,
        AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD => $inputDefaultItemMapping,
        AbstractConfigHelper::IMPORT_ORDER_SINCE => $importOrderSince,
        AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY => $isAllInventorySyncEnabled,
      ];

      $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
        'additionalInfo' => ['payloadOut' => $dataOut],
        'method'         => __METHOD__
      ]);

      return $response->json($dataOut);
=======
      return $response->json(
        [
          AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY => $inputStockBuffer,
          AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY => $inputDefaultShippingProvider,
          AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY => $inputDefaultOrderStatus,
          AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD => $inputDefaultItemMapping,
          AbstractConfigHelper::IMPORT_ORDER_SINCE => $importOrderSince,
          AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY => $isAllInventorySyncEnabled,
        ]
      );
>>>>>>> origin/master
    } catch (ValidationException $e) {
      return $response->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
    }
  }
}
