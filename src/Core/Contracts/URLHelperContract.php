<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

/**
 * Interface for modules that provide URLs for Wayfair infrastructure
 */
interface URLHelperContract {

  // Identifiers for the Wayfair endpoints that the plugin uses
  const URL_ID_GRAPHQL = 'graphql';
  const URL_ID_AUTH = 'auth';
  const URL_ID_PACKING_SLIP = 'packingSlip';
  const URL_ID_SHIPPING_LABEL = 'shippingLabel';

  /**
   * Get the URL for the given key
   * @param string $key
   * @return string
   */
  public function getUrl($key): string;

  /**
   * Get the URL to contact for authentication tokens
   * @return string
   */
  public function getWayfairAuthenticationUrl(): string;

  /**
   * Get packing slip url for PO
   * @param string $poNumber
   *
   * @return string
   */
  public function getPackingSlipUrl(string $poNumber): string;

  /**
   * Finds the Wayfair API audience (Production or Sandbox) for a URL
   * @param string $url the URL that is being checked
   * @return string
   */
  public function getWayfairAudience(string $url);
}
