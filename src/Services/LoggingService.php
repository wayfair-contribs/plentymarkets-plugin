<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Plugin\Log\Loggable;

use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Models\ExternalLogs;

class LoggingService implements LoggerContract
{
  use Loggable;

  const DEBUG = 'DEBUG';
  const INFO = 'INFO';
  const WARNING = 'WARNING';
  const ERROR = 'ERROR';
  const WAYFAIR_PLUGIN_VERSION = 'Wayfair Plugin Version';
  const STRING_LIMIT = 32768;
  const TRUNCATED_SIZE = 1000;
  const LOG_KEY_MESSAGE_TO_LONG = 'messageToLongForPM';

  /**
   * Stores the version of the plugin
   *
   * @var string $version
   */
  public $version;

  /**
   * @var AbstractConfigHelper
   */
  private $configHelper;

  /**
   * Initialize a logging service object
   */
  public function __construct()
  {
    /** @var AbstractConfigHelper */
    $this->configHelper = pluginApp(AbstractConfigHelper::class);
    $this->version = $this->configHelper->getPluginVersion();
  }

  /**
   * Detailed debug information.
   *
   * @param string      $code
   * @param array|null  $loggingInfo
   */
  public function debug(string $code, $loggingInfo = null)
  {

    list($additionalInfo, $method, $referenceType, $referenceValue) = $this->extractVars($loggingInfo);
    $this->getPlentyMarketLoggerInstance($method, $referenceType, $referenceValue)->debug($code, $additionalInfo);
  }

  /**
   * Logs info.
   *
   * @param string      $code
   * @param array|null  $loggingInfo
   */
  public function info(string $code, $loggingInfo = null)
  {

    list($additionalInfo, $method, $referenceType, $referenceValue) = $this->extractVars($loggingInfo);
    $this->getPlentyMarketLoggerInstance($method, $referenceType, $referenceValue)->info($code, $additionalInfo);
  }

  /**
   * Errors that should be logged and monitored.
   *
   * @param string      $code
   * @param array|null  $loggingInfo
   */
  public function error(string $code, $loggingInfo = null)
  {
    list($additionalInfo, $method, $referenceType, $referenceValue) = $this->extractVars($loggingInfo);
    $this->getPlentyMarketLoggerInstance($method, $referenceType, $referenceValue)->error($code, $additionalInfo);
  }

  /**
   * Warnings that should be logged and monitored.
   *
   * @param string      $code
   * @param array|null  $loggingInfo
   */
  public function warning(string $code, $loggingInfo = null)
  {

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
  private function getPlentyMarketLoggerInstance(string $method, string $referenceType = null, int $referenceValue = null)
  {
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
   * Maps data from the associative array that Wayfair provides to the PlentyMarkets logging API's inputs
   *
   * @param $loggingInfo
   *
   * @return array
   */
  public function extractVars($loggingInfo): array
  {
    if (!isset($loggingInfo) || empty($loggingInfo))
    {
      return [];
    }

    /** @var ExternalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    $clientID = $this->configHelper->getClientId();
    $shortMessage = [];
    try {
      $additionalInfo = $loggingInfo['additionalInfo'] ?? [];
      $method = $loggingInfo['method'] ?? null;
      $referenceType = $loggingInfo['referenceType'] ?? null;
      $referenceValue = (int) $loggingInfo['referenceValue'] ?? null;

      if (strlen(json_encode($loggingInfo)) > self::STRING_LIMIT) {
        $logForKibana = [];
        $shortMessage['message'] = self::LOG_KEY_MESSAGE_TO_LONG . $clientID . '-' . date('M d Y H:i:s');
        $additionalInfo = $shortMessage;
        $logForKibana['message'] = self::LOG_KEY_MESSAGE_TO_LONG . $clientID . '-' . date('D, d M Y H:i:s');
        $logForKibana['details'] = $loggingInfo;
        $externalLogs->addErrorLog(json_encode($logForKibana));
      }
      $additionalInfo[self::WAYFAIR_PLUGIN_VERSION] = $this->version;

      return array($additionalInfo, $method, $referenceType, $referenceValue);
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }
}
