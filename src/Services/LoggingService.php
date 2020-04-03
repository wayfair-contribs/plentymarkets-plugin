<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Plugin\Log\Loggable;

use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;

class LoggingService implements LoggerContract {
  use Loggable;

  const DEBUG = 'DEBUG';
  const INFO = 'INFO';
  const WARNING = 'WARNING';
  const ERROR = 'ERROR';

  public function __construct() {
   
  }

  /**
   * Detailed debug information.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function debug(string $code, $loggingInfo = null) {

    if (! $this->canLogLowerThanError())
    {
      return;
    }

    list($additionalInfo, $method, $referenceType, $referenceValue) = $this->extractVars($loggingInfo);
    $this->getPlentyMarketLoggerInstance($method, $referenceType, $referenceValue)->debug($code, $additionalInfo);
  }

  /**
   * Logs info.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function info(string $code, $loggingInfo = null) {

    if (! $this->canLogLowerThanError())
    {
      return;
    }

    list($additionalInfo, $method, $referenceType, $referenceValue) = $this->extractVars($loggingInfo);
    $this->getPlentyMarketLoggerInstance($method, $referenceType, $referenceValue)->info($code, $additionalInfo);
  }

  /**
   * Errors that should be logged and monitored.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function error(string $code, $loggingInfo = null) {
    list($additionalInfo, $method, $referenceType, $referenceValue) = $this->extractVars($loggingInfo);
    $this->getPlentyMarketLoggerInstance($method, $referenceType, $referenceValue)->error($code, $additionalInfo);
  }

  /**
   * Warnings that should be logged and monitored.
   *
   * @param string $code
   * @param null   $loggingInfo
   */
  public function warning(string $code, $loggingInfo = null) {

    if (! $this->canLogLowerThanError())
    {
      return;
    }

    list($additionalInfo, $method, $referenceType, $referenceValue) = $this->extractVars($loggingInfo);
    $this->getPlentyMarketLoggerInstance($method, $referenceType, $referenceValue)->warning($code, $additionalInfo);
  }

  /**
   * @param string      $method
   * @param string|null $referenceType
   * @param int|null    $referenceValue
   *
   * @return \Plenty\Log\Contracts\LoggerContract
   */
  private function getPlentyMarketLoggerInstance(string $method, string $referenceType = null, int $referenceValue = null) {
    $pmLoggerInstance = $this->getLogger($method);
    if (isset($referenceValue)) {
      $pmLoggerInstance = $pmLoggerInstance->setReferenceValue($referenceValue);
    }
    if (isset($referenceType)) {
      $pmLoggerInstance = $pmLoggerInstance->setReferenceType($referenceType);
    }

    return $pmLoggerInstance;
  }

  /**
   * @param $loggingInfo
   *
   * @return array
   */
  public function extractVars($loggingInfo): array {
    $additionalInfo = $loggingInfo['additionalInfo'] ?? [];
    $method = $loggingInfo['method'] ?? null;
    $referenceType = $loggingInfo['referenceType'] ?? null;
    $referenceValue = (int) $loggingInfo['referenceValue'] ?? null;

    return array($additionalInfo, $method, $referenceType, $referenceValue);
  }

  /**
   * Checks if it is alright to log at a level lower than ERROR.
   * Logging at levels such as INFO and DEBUG prior to the end of the "boot" period
   * Causes issues.
   * 
   * See https://forum.plentymarkets.com/t/wayfair-log-levels-info-and-debug-not-working-for-loggable-module/581320/22
   *
   * @return boolean
   */
  private function canLogLowerThanError(): bool {
    /**
     * @var AbstractConfigHelper $configHelper
     */
    $configHelper = pluginApp(AbstractConfigHelper::class);
    return $configHelper->hasBooted();
  }
}
