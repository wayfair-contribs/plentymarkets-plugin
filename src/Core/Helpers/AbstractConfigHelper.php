<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
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
  const FULL_INVENTORY_CRON_STATUS = 'full_inventory_cron_status';
  const FULL_INVENTORY_STATUS_UPDATED_AT = 'full_inventory_status_updated_at';
  const FULL_INVENTORY_CRON_RUNNING = 'running';
  const FULL_INVENTORY_CRON_IDLE = 'idle';
  const SECONDS_INTERVAL_FOR_INVENTORY = 4800;
  const SHIPPING_METHOD = 'shippingMethod';
  const PAYMENT_METHOD_INVOICE = 2;
  const WAYFAIR_INTEGRATION_HEADER = 'Wayfair-Integration-Agent';
  const INVENTORY_ITEMS_PER_PAGE = 500;

  /**
   * Retrieve the configuration client id for the global client
   *
   * @return mixed
   */
  abstract public function getClientId();

  /**
   * Retrieve the configuration client secret for the global client
   * @return mixed
   */
  abstract public function getClientSecret();

  /**
   * Checks if an item already exists in cache and returns the key,
   * if the item does not exists in cache adds item to cache
   *
   * @return int
   */
  abstract public function getOrderReferrerValue(): int;

  /**
   * @return mixed
   */
  abstract public function getStockBufferValue();

  /**
   * Retrieve the configuration values for setting up a test environment
   *
   * @return string
   */
  abstract public function getDryRun(): string;

  /**
   * @return bool
   */
  abstract public function isAllItemsActive(): bool;

  /**
   * Check if boot is completed
   *
   * @return bool
   */
  abstract public function hasBooted(): bool;

  /**
   * Retrieves the current Wayfair plugin version
   *
   * @return string
   */
  abstract public function getPluginVersion(): string;

}
