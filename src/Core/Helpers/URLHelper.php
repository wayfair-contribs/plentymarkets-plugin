<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Helpers;

use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\URLHelperContract;
use Wayfair\Helpers\TranslationHelper;

class URLHelper implements URLHelperContract{

  // Base URLs for use in this module.
  // cannot mark private as plentymarkets is only at PHP 7.0
  const BASE_URL_AUTH = 'https://sso.auth.wayfair.com/';
  const BASE_URL_API = 'https://api.wayfair.com/';
  const BASE_URL_SANDBOX = 'https://sandbox.api.wayfair.com/';

  const LOG_KEY_TEST_MODE_OFF = 'testModeOff';
  const LOG_KEY_TEST_MODE_ON = 'testModeOn';
  
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
  public function getUrl($key): string {
    $base = self::getBaseUrl($key);
    // TODO: log about unknown URL path
    return $base . self::URL_PATH[$key];
  }

  /**
   * Get the URL to contact for authentication tokens
   * @return string
   */
  public function getWayfairAuthenticationUrl(): string {
    return self::getBaseUrl(self::URL_ID_AUTH) . self::URL_PATH[self::URL_ID_AUTH];
  }

  /**
   * Get the URL to the wayfair API
   * The returned value depends on the plugin's configuration, and may point to the API Sandbox.
   * @param string $key - the identifier of the endpoint that the URL is needed for
   * @return string
   */
  private static function getBaseUrl($key = NULL): string {

    if ($key === self::URL_ID_AUTH)
    {
      // prod and sandbox use the same auth service
      return self::BASE_URL_AUTH;
    }

    /**
     * @var ConfigHelperContract $configHelper
     */
    $configHelper = pluginApp(ConfigHelperContract::class);

    /**
     * @var LoggerContract $logger
     */
    $logger = pluginApp(LoggerContract::class);
    
    if ($configHelper->isTestingEnabled())
    {
      $logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_TEST_MODE_ON));
      return self::BASE_URL_SANDBOX;
    }

    $logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_TEST_MODE_OFF));
    return self::BASE_URL_API;
  }

  /**
   * Get packing slip url for PO, such as:
   * https://api.wayfair.com/v1/packing_slip/UK190380850
   * @param string $poNumber
   *
   * @return string
   */
  public function getPackingSlipUrl(string $poNumber): string {
    return self::getBaseUrl(self::URL_ID_PACKING_SLIP) . 'v1/packing_slip/' . $poNumber;
  }

  /**
   * Finds the Wayfair API audience (Production or Sandbox) for a URL
   * @param string $url the URL that is being checked
   * @return string
   */
  public function getWayfairAudience(string $url) {
    // URL must START with one of the approved URLs.
    // otherwise, someone can put our URL into the query string and trick us into sending the auth header.
    foreach (self::URLS_USING_WAYFAIR_AUTH as $wayfair_authenticated_domain)
    {
      if (stripos($url, $wayfair_authenticated_domain) == 0)
      {
        return $url;
      }
    }

    return null;
  }

}
