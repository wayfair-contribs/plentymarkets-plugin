<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface LoggerContract {

  /**
   * Detailed debug information.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function debug(string $code, $loggingInfo = null);

  /**
   * Logs info.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function info(string $code, $loggingInfo = null);

  /**
   * Errors that should be logged and monitored.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function error(string $code, $loggingInfo = null);

  /**
   * Warnings that should be logged and monitored.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function warning(string $code, $loggingInfo = null);

}
