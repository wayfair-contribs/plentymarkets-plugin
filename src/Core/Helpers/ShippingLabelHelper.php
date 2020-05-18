<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Helpers;

/**
 * Class ShippingLabelHelper
 *
 * @package Wayfair\Core\Helpers
 */
class ShippingLabelHelper {
  /**
   * Generate file name for shipping label, default to PDF file.
   *
   * @param string $poNumber
   * @param mixed  $packageId
   * @param string $extension
   *
   * @return string
   */
  public static function generateLabelFileName(string $poNumber, $packageId, $extension = 'pdf'): string {
    return self::generateShipmentNumber($poNumber, $packageId) . ".{$extension}";
  }

  /**
   * @param string $poNumber
   * @param mixed  $packageId
   *
   * @return string
   */
  public static function generateShipmentNumber(string $poNumber, $packageId): string {
    return "{$poNumber}_{$packageId}";
  }

  /**
   * Remove first 2 characters of full PoNumber and return just integer part.
   *
   * @param string $poNumber
   *
   * @return int
   */
  public static function removePoNumberPrefix(string $poNumber): int {
    return (int)substr($poNumber, 2);
  }
}
