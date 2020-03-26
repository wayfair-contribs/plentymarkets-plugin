<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Helpers;

use Wayfair\Core\Helpers\AbstractConfigHelper;

class URLHelper {

  // Base URLs for use in this module.
  // cannot mark private as plentymarkets is only at PHP 7.0
  const BASE_URL_AUTH = 'https://sso.auth.wayfair.com/';
  const BASE_URL_API = 'https://api.wayfair.com/';
  const BASE_URL_SANDBOX = 'https://sandbox.api.wayfair.com/';

  // Identifiers for the Wayfair endpoints that the plugin uses
  const URL_ID_GRAPHQL = 'graphql';
  const URL_ID_AUTH = 'auth';
  const URL_ID_PACKING_SLIP = 'packingSlip';
  const URL_ID_SHIPPING_LABEL = 'shippingLabel';
  
  // paths to append to appropriate base URLs
  const URL_PATH = [
      self::URL_ID_GRAPHQL => 'v1/graphql',
      self::URL_ID_AUTH    => 'oauth/token',
      self::URL_ID_PACKING_SLIP => 'v1/packing_slip/',
  ];

  // whitelisting URLs for the sending of Wayfair Auth header values
  const URLS_USING_WAYFAIR_AUTH = [ 
    self::BASE_URL_API,
    self::BASE_URL_SANDBOX
  ];

  /**
   * @param string $key
   *
   * @return string
   */
  public static function getUrl($key) {
    $base = self::getBaseUrl($key);
    // TODO: log about unknown URL path
    return $base . self::URL_PATH[$key];
  }

  /**
   * Get the URL to contact for authentication tokens
   * @return string
   */
  public static function getAuthUrl(): string {
    return self::getBaseUrl(self::URL_ID_AUTH) . self::URL_PATH[self::URL_ID_AUTH];
  }

  /**
   * Get the URL to the wayfair API
   * The returned value depends on the plugin's configuration, and may point to the API Sandbox.
   * @param string $key - the identifier of the endpoint that the URL is needed for
   * @return string
   */
  public static function getBaseUrl($key = NULL): string {

    if (is_null($key))
    {
      // TODO: log about defaulting to GraphQL
      $key = self::URL_ID_GRAPHQL;
    }

    if ($key === Self::URL_ID_AUTH)
    {
      // prod and sandbox use the same auth service
      return self::BASE_URL_AUTH;
    }

    /**
     * @var AbstractConfigHelper $configHelper
     */
    $configHelper = pluginApp(AbstractConfigHelper::class);

    if ($configHelper->isTestingEnabled())
    {
      return self::BASE_URL_SANDBOX;
    }

    return self::BASE_URL_API;
  }

  /**
   * Get packing slip url for PO, such as:
   * https://api.wayfair.com/v1/packing_slip/UK190380850
   * @param string $poNumber
   *
   * @return string
   */
  public static function getPackingSlipUrl(string $poNumber): string {
    return self::getBaseUrl(self::URL_ID_PACKING_SLIP) . 'v1/packing_slip/' . $poNumber;
  }

  /**
   * Checks if it is appropriate to use the wayfair OAuth token when making a request to a URL
   * @param string $url the URL that is being checked
   * @return bool
   */
  public static function usesWayfairAuthToken(string $url): bool {
    // URL must START with one of the approved URLs.
    // otherwise, someone can put our URL into the query string and trick us into sending the auth header.
    foreach (self::URLS_USING_WAYFAIR_AUTH as $wayfair_authenticated_domain)
    {
      if (strpos($url, $wayfair_authenticated_domain) == 0)
      {
        return true;
      }
    }

    return false;
  }

}
