<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Order\Status\Contracts\OrderStatusRepositoryContract;
use Plenty\Modules\Order\Status\Models\OrderStatus;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

/**
 * Controller for the "Settings" tab in the Wayfair plugin
 */
class SettingsController
{
  const LOG_KEY_CONTROLLER_IN = "controllerInput";
  const LOG_KEY_CONTROLLER_OUT = "controllerOutput";
  const LOG_KEY_SETTING_MAPPING_METHOD = "settingMappingMethod";
  const LOG_KEY_INVALID_SETTINGS = "invalidSettings";
  const LOG_KEY_SAVE_FAILED= "couldNotSaveSettings";

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var AbstractConfigHelper
   */
  private $configHelper;

  /**
   * @var LoggerContract
   */
  private $logger;

  /**
   * SettingsController constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   * @param AbstractConfigHelper $configHelper
   * @param LoggerContract $logger
   */
  public function __construct(KeyValueRepository $keyValueRepository, AbstractConfigHelper $configHelper, LoggerContract $logger)
  {
    $this->keyValueRepository = $keyValueRepository;
    $this->logger = $logger;
    $this->configHelper = $configHelper;
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
      AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY => (!isset($stockBuffer) || empty($stockBuffer) ? 0 : $stockBuffer),
      AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY => (!isset($defaultOrderStatus) || empty($defaultOrderStatus) ? null : $defaultOrderStatus),
      AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY => (!isset($defaultShippingProvider) || empty($defaultShippingProvider) ? null : $defaultShippingProvider),
      AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD => (!isset($defaultItemMappingMethod) || empty($defaultItemMappingMethod) ? AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER : $defaultItemMappingMethod),
      AbstractConfigHelper::IMPORT_ORDER_SINCE => (!isset($orderImportDate) || empty($orderImportDate) ? '' : $orderImportDate),
      AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY => (!isset($isAllInventorySyncEnabled) || empty($isAllInventorySyncEnabled) ? false : $isAllInventorySyncEnabled),
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
    $settingMappings = null;
    try {
      $dataIn = $request->input('data');

      $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_IN), [
        'additionalInfo' => ['payloadIn' => json_encode($dataIn)],
        'method'         => __METHOD__
      ]);

      if (!isset($dataIn) || empty($dataIn)) {
        throw new \Exception('No settings data provided');
      }

      // get and validate all user input
      $stockBuffer = self::getAndValidateStockBufferFromInput($dataIn);
      $defaultShippingProviderId = self::getAndValidateDefaultShippingProviderFromInput($dataIn);
      $defaultOrderStatusId = self::getAndValidateDefaultOrderStatusFromInput($dataIn);
      $defaultItemMappingMethod = $this->getAndValidateDefaultItemMappingMethodFromInput($dataIn);
      $importOrderSince = self::getAndValidateImportOrdersSinceFromInput($dataIn);
      $isAllInventorySyncEnabled = self::getAndValidateAllInventorySyncFromInput($dataIn);

      $settingMappings = [
        AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY => $stockBuffer,
        AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY => $defaultShippingProviderId,
        AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY => $defaultOrderStatusId,
        AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD => $defaultItemMappingMethod,
        AbstractConfigHelper::IMPORT_ORDER_SINCE => $importOrderSince,
        AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY => $isAllInventorySyncEnabled,
      ];

    } catch (\Exception $e) {

      $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_SETTINGS), [
        'additionalInfo' => [
          'error' => $e->getMessage()
        ],
        'method'         => __METHOD__
      ]);

      return $response->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
    }

    try {
      // store the mappings down into the plugin's data repository

      $this->logger->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_SETTING_MAPPING_METHOD),
        [
          'additionalInfo' => ['mappingMethod' => $defaultItemMappingMethod],
          'method' => __METHOD__
        ]
      );

      foreach ($settingMappings as $key => $value) {
        $this->keyValueRepository->putOrReplace($key, $value);
      }

      // send the validated, in-memory settings back out to the client
      $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
        'additionalInfo' => ['payloadOut' => $settingMappings],
        'method'         => __METHOD__
      ]);

      return $response->json($settingMappings);
    } catch (\Exception $e) {

      $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_SAVE_FAILED), [
        'additionalInfo' => [
          'error' => $e->getMessage()
        ],
        'method'         => __METHOD__
      ]);

      return $response->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Get the Stock Buffer value from a payload
   *
   * @param mixed $inputData
   * @return int
   * @throws ValidationException
   */
  private static function getAndValidateStockBufferFromInput($inputData)
  {
    $inputStockBuffer = $inputData[AbstractConfigHelper::SETTINGS_STOCK_BUFFER_KEY];

    if (!isset($inputStockBuffer) || empty($inputStockBuffer)) {
      return 0;
    }

    if (!is_numeric($inputStockBuffer) || $inputStockBuffer < 0) {
      throw new ValidationException('When provided, Stock Buffer must be a non-negative number');
    }

    return (int) $inputStockBuffer;
  }

  /**
   * Get the Default Shipping Provider value from a payload
   *
   * @param mixed $inputData
   * @return int|null
   * @throws ValidationException
   */
  private static function getAndValidateDefaultShippingProviderFromInput($inputData)
  {
    $inputDefaultShippingProvider = $inputData[AbstractConfigHelper::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY];

    // Default Shipping Provider is deprecated in versions 1.1.2 and up - null is OK.
    if (!isset($inputDefaultShippingProvider) || empty($inputDefaultShippingProvider)) {
      return null;
    }

    if (!is_numeric($inputDefaultShippingProvider) || $inputDefaultShippingProvider < 0) {
      throw new ValidationException('When provided, Default Shipping Provider must be a non-negative number');
    }

    return (int) $inputDefaultShippingProvider;
  }

  /**
   * Get the Default Order Status value from a payload
   *
   * @param mixed $inputData
   * @return float|null
   * @throws ValidationException
   */
  private static function getAndValidateDefaultOrderStatusFromInput($inputData)
  {
    $inputDefaultOrderStatus = $inputData[AbstractConfigHelper::SETTINGS_DEFAULT_ORDER_STATUS_KEY];

    // this is nullable - see PurchaseOrderMapper
    if (!isset($inputDefaultOrderStatus) || empty($inputDefaultOrderStatus)) {
      return null;
    }

    if (!is_numeric($inputDefaultOrderStatus) || $inputDefaultOrderStatus < OrderStatus::ORDER_STATUS_MIN_VALUE || $inputDefaultOrderStatus > OrderStatus::ORDER_STATUS_MAX_VALUE) {
      throw new ValidationException('When set, Order Status ID must be a number between ' . OrderStatus::ORDER_STATUS_MIN_VALUE . " and " . OrderStatus::ORDER_STATUS_MAX_VALUE);
    }

    $orderStatus = (float) $inputDefaultOrderStatus;

    /** @var OrderStatusRepositoryContract */
    $orderStatusRepository = pluginApp(OrderStatusRepositoryContract::class);

    $statusModel = null;

    try {
      $statusModel = $orderStatusRepository->get($orderStatus);
    } catch (\Exception $exception) {
      // exception is expected when Plentymarkets can't find the row in the DB
    }

    if (!isset($statusModel)) {
      throw new ValidationException('No Order Status found with ID: ' . $orderStatus);
    }

    return $orderStatus;
  }

  /**
   * Get the Default Item Mapping Method value from a payload
   *
   * @param mixed $inputData
   * @return string
   * @throws ValidationException
   */
  private function getAndValidateDefaultItemMappingMethodFromInput($inputData)
  {
    $inputDefaultItemMappingMethod = $inputData[AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD];

    if (!$this->configHelper->validateItemMappingMethod($inputDefaultItemMappingMethod)) {
      throw new ValidationException('The chosen Item Mapping Method is not recognized');
    }

    return $inputDefaultItemMappingMethod;
  }

  /**
   * Get the Import Orders Since value from a payload
   *
   * @param mixed $inputData
   * @return string|null
   * @throws ValidationException
   */
  private static function getAndValidateImportOrdersSinceFromInput($inputData)
  {
    $importOrderSince = $inputData[AbstractConfigHelper::IMPORT_ORDER_SINCE];

    // this is nullable - all orders can be imported
    if (!isset($importOrderSince) || empty($importOrderSince)) {
      return null;
    }

    $date = null;
    try {
      $date = \DateTime::createFromFormat('Y-m-d', $importOrderSince);
    } catch (\Exception $e) {
      throw new ValidationException("When set, Import Orders Since setting must be a valid date", 400, $e);
    }

    if (!isset($date) || $date->format('Y-m-d') != $importOrderSince) {
      throw new ValidationException("When set, Import Orders Since setting must be a valid date");
    }

    return $importOrderSince;
  }

  /**
   * Get the 'Send all inventory items to Wayfair?' value from a payload
   *
   * @param mixed $inputData
   * @return bool
   * @throws ValidationException
   */
  private static function getAndValidateAllInventorySyncFromInput($inputData)
  {
    $isAllInventorySyncEnabled = $inputData[AbstractConfigHelper::SETTINGS_SEND_ALL_ITEMS_KEY];

    // normalize null to false
    return isset($isAllInventorySyncEnabled) && $isAllInventorySyncEnabled;
  }
}
