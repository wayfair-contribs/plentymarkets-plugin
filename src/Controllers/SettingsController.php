<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Repositories\KeyValueRepository;

class SettingsController {

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * StockBufferController constructor.
   *
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository) {
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * Get current Stock Buffer value.
   *
   * @return false|string
   */
  public function get() {
    $stockBuffer = $this->keyValueRepository->get(ConfigHelperContract::SETTINGS_STOCK_BUFFER_KEY);
    $defaultOrderStatus = $this->keyValueRepository->get(ConfigHelperContract::SETTINGS_DEFAULT_ORDER_STATUS_KEY);
    $defaultShippingProvider = $this->keyValueRepository->get(ConfigHelperContract::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY);
    $defaultItemMappingMethod = $this->keyValueRepository->get(ConfigHelperContract::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);
    $orderImportDate = $this->keyValueRepository->get(ConfigHelperContract::IMPORT_ORDER_SINCE);
    $isAllInventorySyncEnabled = $this->keyValueRepository->get(ConfigHelperContract::SETTINGS_SEND_ALL_ITEMS_KEY);

    $data = [
        ConfigHelperContract::SETTINGS_STOCK_BUFFER_KEY => (empty($stockBuffer) ? 0 : $stockBuffer),
        ConfigHelperContract::SETTINGS_DEFAULT_ORDER_STATUS_KEY => (empty($defaultOrderStatus) ? null : $defaultOrderStatus),
        ConfigHelperContract::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY => (empty($defaultShippingProvider) ? null : $defaultShippingProvider),
        ConfigHelperContract::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD => (empty($defaultItemMappingMethod) ? ConfigHelperContract::ITEM_MAPPING_VARIATION_NUMBER : $defaultItemMappingMethod),
        ConfigHelperContract::IMPORT_ORDER_SINCE => (empty($orderImportDate) ? '' : $orderImportDate),
        ConfigHelperContract::SETTINGS_SEND_ALL_ITEMS_KEY => (empty($isAllInventorySyncEnabled) ? false : $isAllInventorySyncEnabled),
    ];

    return json_encode($data);
  }

  /**
   * Save stock buffer to key/value storage
   *
   * @param Request  $request
   * @param Response $response
   *
   * @return false|string
   */
  public function post(Request $request, Response $response) {
    $data = $request->input('data');
    if (!is_numeric($data[ConfigHelperContract::SETTINGS_STOCK_BUFFER_KEY])) {
      return $response->json(['error' => 'Stock Buffer must be a number'], Response::HTTP_BAD_REQUEST);
    }

    if (!is_numeric($data[ConfigHelperContract::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY])) {
      return $response->json(['error' => 'Shipping Provider ID must be a number'], Response::HTTP_BAD_REQUEST);
    }

    if (!is_numeric($data[ConfigHelperContract::SETTINGS_DEFAULT_ORDER_STATUS_KEY])) {
      return $response->json(['error' => 'Order Status ID must be be a number'], Response::HTTP_BAD_REQUEST);
    }

    if (empty($data[ConfigHelperContract::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD])
        || !in_array(
            $data[ConfigHelperContract::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD],
            [
                ConfigHelperContract::ITEM_MAPPING_VARIATION_NUMBER,
                ConfigHelperContract::ITEM_MAPPING_EAN,
                ConfigHelperContract::ITEM_MAPPING_SKU
            ]
        )) {
      return $response->json(['error' => 'Item mapping must be from the selection only'], Response::HTTP_BAD_REQUEST);
    }

    if (isset($data[ConfigHelperContract::IMPORT_ORDER_SINCE])) {
      $date = \DateTime::createFromFormat('Y-m-d', $data[ConfigHelperContract::IMPORT_ORDER_SINCE]);
      if (!$date || $date->format('Y-m-d') != $data[ConfigHelperContract::IMPORT_ORDER_SINCE]) {
        return $response->json(['error' => 'Import orders since: value is incorrect'], Response::HTTP_BAD_REQUEST);
      }
    }

    $inputStockBuffer = (int)$data[ConfigHelperContract::SETTINGS_STOCK_BUFFER_KEY];
    $inputDefaultShippingProvider = (int)$data[ConfigHelperContract::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY];
    $inputDefaultOrderStatus = (int)$data[ConfigHelperContract::SETTINGS_DEFAULT_ORDER_STATUS_KEY];
    $inputDefaultItemMapping = $data[ConfigHelperContract::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD];
    $importOrderSince = $data[ConfigHelperContract::IMPORT_ORDER_SINCE];
    $isAllInventorySyncEnabled = isset($data[ConfigHelperContract::SETTINGS_SEND_ALL_ITEMS_KEY]) && $data[ConfigHelperContract::SETTINGS_SEND_ALL_ITEMS_KEY];

    if ($inputStockBuffer < 0) {
      return $response->json(['error' => 'Stock Buffer cannot be negative'], Response::HTTP_BAD_REQUEST);
    }

    try {
      $this->keyValueRepository->putOrReplace(ConfigHelperContract::SETTINGS_STOCK_BUFFER_KEY, $inputStockBuffer);
      $this->keyValueRepository->putOrReplace(ConfigHelperContract::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY, $inputDefaultShippingProvider);
      $this->keyValueRepository->putOrReplace(ConfigHelperContract::SETTINGS_DEFAULT_ORDER_STATUS_KEY, $inputDefaultOrderStatus);
      $this->keyValueRepository->putOrReplace(ConfigHelperContract::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD, $inputDefaultItemMapping);
      $this->keyValueRepository->putOrReplace(ConfigHelperContract::IMPORT_ORDER_SINCE, $importOrderSince);
      $this->keyValueRepository->putOrReplace(ConfigHelperContract::SETTINGS_SEND_ALL_ITEMS_KEY, $isAllInventorySyncEnabled);

      return $response->json(
          [
              ConfigHelperContract::SETTINGS_STOCK_BUFFER_KEY => $inputStockBuffer,
              ConfigHelperContract::SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY => $inputDefaultShippingProvider,
              ConfigHelperContract::SETTINGS_DEFAULT_ORDER_STATUS_KEY => $inputDefaultOrderStatus,
              ConfigHelperContract::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD => $inputDefaultItemMapping,
              ConfigHelperContract::IMPORT_ORDER_SINCE => $importOrderSince,
              ConfigHelperContract::SETTINGS_SEND_ALL_ITEMS_KEY => $isAllInventorySyncEnabled,
          ]
      );
    } catch (ValidationException $e) {
      return $response->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
    }
  }
}
