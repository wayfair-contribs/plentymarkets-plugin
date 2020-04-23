<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Libs;

trait CreateDTOFromArrayTrait {
  /**
   * Static function to create a new Response DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self {
    $dto = new self();
    foreach ($params as $key => $value) {
      $functionName = 'set' . ucfirst($key);
      if (method_exists($dto, $functionName)) {
        $dto->$functionName($value);
      }
    }
    return $dto;
  }
}