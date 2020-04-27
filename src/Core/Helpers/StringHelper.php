<?php
/**
 * String Helpers
 *
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Helpers;

class StringHelper {

  /**
   * Function that hides characters in a string, will default to the first and last characters being visible.
   *
   * @param string $string The to string to be masked
   * @param mixed $start The position to start masking, default to position 1
   * @param mixed $finish The position to stop masking, default to position -1
   *
   * @return string
   */
  public static function maskString($string, $start = 1, $finish = -1 ) {
    $mask = preg_replace("/\S/", "*", $string);

    if (empty($string)){
      return '';
    }

    $mask = substr ( $mask, $start, $finish );
    $str = substr_replace ( $string, $mask, $start, $finish );
    return $str;
  }
}
