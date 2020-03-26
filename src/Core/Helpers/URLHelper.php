<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Helpers;

class URLHelper {
  // Production Base URLs
  const BASE_URL = 'https://api.wayfair.com/';
  const BASE_AUTH_URL = 'https://sso.auth.wayfair.com/';

  // URLs
  const URL_GRAPHQL = 'graphql';
  const URL_AUTH = 'auth';
  const URLS = [
      self::URL_GRAPHQL => 'v1/graphql',
      self::URL_AUTH    => 'oauth/token'
  ];

  /**
   * @param string $key
   *
   * @return string
   */
  public static function getUrl($key) {
    $base = self::getBaseUrl();
    return $base . self::URLS[$key];
  }

  /**
   * @return string
   */
  public static function getAuthUrl() {
    return self::BASE_AUTH_URL . self::URLS[self::URL_AUTH];
  }

  /**
   * @return string
   */
  public static function getBaseUrl() {
    return self::BASE_URL;
  }

  /**
   * Get packing slip url for PO, such as:
   * https://api.wayfair.com/v1/packing_slip/UK190380850
   * @param string $poNumber
   *
   * @return string
   */
  public static function getPackingSlipUrl(string $poNumber) {
    return self::BASE_URL . 'v1/packing_slip/' . $poNumber;
  }

}
