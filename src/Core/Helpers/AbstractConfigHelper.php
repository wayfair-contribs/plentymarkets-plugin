<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Helpers;

abstract class AbstractConfigHelper {
  const PLUGIN_NAME = 'Wayfair';
  const SHIPPING_PROVIDER_NAME = 'WayfairShipping';
  const SETTINGS_ORDER_REFERRER_KEY = 'orderReferrer';
  const SETTINGS_STOCK_BUFFER_KEY = 'stockBuffer';
  const SETTINGS_DEFAULT_ORDER_STATUS_KEY = 'defaultOrderStatus';
  const SETTINGS_DEFAULT_SHIPPING_PROVIDER_KEY = 'defaultShippingProvider';
  const IMPORT_ORDER_SINCE = 'importOrdersSince';
  const SETTINGS_SEND_ALL_ITEMS_KEY = 'isAllInventorySyncEnabled';
  const SHIPPING_PROVIDER_ID = 'SHIPPING_PROVIDER_ID';
  const PAYMENT_TRANSACTION_TYPE_BOOKED_PAYMENT = 2;
  const PAYMENT_KEY = 'PAYMENT_WAYFAIR';
  const BILLING_CONTACT = 'BILLING_CONTACT';
  const SETTINGS_DEFAULT_ITEM_MAPPING_METHOD = 'defaultItemMappingMethod';
  const ITEM_MAPPING_EAN = 'barcode';
  const ITEM_MAPPING_VARIATION_NUMBER = 'numberExact';
  const ITEM_MAPPING_SKU = 'sku';
  const SHIPPING_METHOD = 'shippingMethod';
  const PAYMENT_METHOD_INVOICE = 2;
  const WAYFAIR_INTEGRATION_HEADER = 'Wayfair-Integration-Agent';
  const INTEGRATION_AGENT_NAME = 'PlentyMarket';

  /**
   * Retrieve the configured client ID for connections to Wayfair's secure APIs
   *
   * @return mixed
   */
  abstract public function getClientId();

  /**
   * Retrieve the configured client secret for connections to Wayfair's secure APIs
   *
   * @return mixed
   */
  abstract public function getClientSecret();

  /**
   * Returns the PlentyMarkets identifier for Wayfair's Order Referrer value
   *
   * @return float
   */
  abstract public function getOrderReferrerValue(): float;

  /**
   * Get the value of the user-configured Stock Buffer setting
   * @return mixed
   */
  abstract public function getStockBufferValue();

  /**
   * Checks if the plugin is configured to use the test / dryRun mode when communicating with the Wayfair APIs
   *
   * @return string
   */
  abstract public function getDryRun(): string;

  /**
   * Returns the value of the user-configured "send all inventory items to Wayfair" setting
   * @return bool
   */
  abstract public function isAllItemsActive(): bool;

  /**
   * Retrieves the current Wayfair plugin version
   *
   * @return string
   */
  abstract public function getPluginVersion(): string;

   /**
   * Retrieves the item mapping mode for Inventory
   *
   * @return string
   */
  abstract public function getItemMappingMethod();

  /**
   * Check an Item Mapping Method choice against known values
   *
   * @param string $itemMappingMethod
   * @return bool
   */
  abstract public function validateItemMappingMethod($itemMappingMethod);
}
