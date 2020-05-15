<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */
namespace Wayfair\Core\Helpers;

class CamelToSnakeCaseHelper
{
  /**
   * Function to convert CamelCase to SnakeCase e.g supplierId to supplier_id
   *
   * @param string $input
   *
   * @return string
   */
  public static function execute($input)
  {
    preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
    $ret = $matches[0];
    foreach ($ret as &$match) {
      $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
    }
    return implode('_', $ret);
  }
}
