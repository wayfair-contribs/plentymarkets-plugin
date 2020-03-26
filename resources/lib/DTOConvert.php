<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Libs;

use PhpDocReader\PhpDocReader;
use Wayfair\Core\Helpers\CamelToSnakeCaseHelper;

class DTOConvert {
  /**
   * @param string $className
   *
   * @throws \Exception
   *
   * @return array
   */
  public static function dtoToArray($className): array {
    if (!class_exists($className)) {
      throw new \Exception('Class ' . $className . ' does not exist!');
    }
    $data = [];
    $refClass = new \ReflectionClass($className);
    $reader = new PhpDocReader();
    foreach ($refClass->getProperties(\ReflectionProperty::IS_PRIVATE) as $refProperty) {
      $propertyName = $refProperty->getName();
      $propertyClass = $reader->getPropertyClass($refProperty);
      if ($propertyClass) {
        $data[$propertyName] = self::dtoToArray($propertyClass);
      } else {
        $data[] = $refProperty->getName();
      }
    }
    return $data;
  }

  /**
   * @param $instance
   * @param $isSnakeCase
   *
   * @return array
   * @throws \Exception
   */
  public static function dtoInstanceToArray($instance, $isSnakeCase) {
    if (!is_object($instance)) {
      throw new \Exception('Instance ' . $instance . ' does not exist!');
    }
    $data = [];
    $refObject = new \ReflectionObject($instance);
    foreach ($refObject->getProperties(\ReflectionProperty::IS_PRIVATE) as $refProperty) {
      $propertyName = $refProperty->getName();
      $propertyValue = $refProperty->getValue();
      if ($isSnakeCase) {
        $propertyName = CamelToSnakeCaseHelper::execute($propertyName);
      }
      if (is_object($propertyValue)) {
        $data[$propertyName] = self::dtoInstanceToArray($propertyValue, $isSnakeCase);
      } else {
        $data[$propertyName] = $refProperty->getValue();
      }
    }
    return $data;
  }

  public static function arrayToGraphQLMutation($data,  $isSnakeCase = false): string {
    $string_queue = [];

    if (is_array($data)) {
      foreach ($data as $key => $value) {
        $propertyName = $isSnakeCase ? CamelToSnakeCaseHelper::execute($key) : $key;
        $propertyValue = $value;
        if (is_array($value)) {
          array_push($string_queue, $propertyName . ' : ' .self::arrayToGraphQLMutation($propertyValue));
        } else {
          if (is_bool($propertyValue)) {
            $propertyValue = $propertyValue ? 'true' : 'false';
          } elseif ( is_string($propertyValue)) {
            $propertyValue = '"' . $propertyValue . '"';
          }
          array_push($string_queue, $propertyName . ' : ' . $propertyValue);
        }
      }
    }

    return join(', ', $string_queue);
  }

  /**
   * @param array $data
   *
   * @return string
   */
  public static function arrayToGraphQLQuery($data): string {
    $result = '';
    foreach ($data as $key => $value) {
      if ($result) {
        $result .= ', ';
      }
      if (is_array($value)) {
        $result .= ($key . ' {' . self::arrayToGraphQLQuery($value) . ' }' );
      } else {
        $result .= $value;
      }
    }
    return $result;
  }

  /**
   * @param string $className
   *
   * @throws \Exception
   *
   * @return string
   */
  public static function dtoToGraphQLQuery($className) {
    return self::arrayToGraphQLQuery(self::dtoToArray($className));
  }

  /**
   * @param      $dtoObject
   * @param bool $isSnakeCase
   *
   * @return string
   * @throws \Exception
   */
  public static function dtoInstanceToGraphQLMutation($dtoObject, $isSnakeCase = false) {
    return self::arrayToGraphQLMutation(self::dtoInstanceToArray($dtoObject, $isSnakeCase));
  }
}
