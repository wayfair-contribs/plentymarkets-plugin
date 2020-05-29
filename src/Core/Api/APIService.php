<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api;

use Wayfair\Core\Contracts\AuthContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\URLHelperContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Helpers\StringHelper;
use Wayfair\Http\WayfairResponse;

class APIService
{
  const LOG_KEY_API_SERVICE = 'apiService';
  const LOG_KEY_API_SERVICE_ERROR = 'apiServiceError';
  const HEADER_KEY_AUTHORIATION = 'Authorization';
  const HEADER_KEY_CONTENT_TYPE = 'Content-Type';
  const MIME_TYPE_JSON = 'application/json';

  /**
   * @var AuthContract
   */
  protected $authService;

  /**
   * @var ClientInterfaceContract
   */
  protected $client;

  /**
   * @var LoggerContract $loggerContract
   */
  protected $loggerContract;

  /**
   * @var ConfigHelperContract
   */
  protected $configHelper;

  /** 
   * @var URLHelperContract
  */
  protected $urlHelper;

  /**
   * @param ClientInterfaceContract $clientInterfaceContract
   * @param AuthContract  $authContract
   * @param ConfigHelperContract    $configHelper
   * @param LoggerContract          $loggerContract
   * @param URLHelperContract       $urlHelper
   */
  public function __construct(
    ClientInterfaceContract $clientInterfaceContract,
    AuthContract $authContract,
    ConfigHelperContract $configHelper,
    LoggerContract $loggerContract,
    URLHelperContract $urlHelper
  ) {
    $this->client = $clientInterfaceContract;
    $this->authService = $authContract;
    $this->configHelper = $configHelper;
    $this->loggerContract = $loggerContract;
    $this->urlHelper = $urlHelper;
  }

  /**
   * @param string $query
   * @param string $method
   * @param array  $variables
   *
   * @throws \Exception
   * @return WayfairResponse
   */
  public function query($query, $method = 'post', $variables = [])
  {
    try {
      $url = $this->getUrl();
      $authHeaderVal = $this->authService->generateAuthHeader($url);

      if (!isset($authHeaderVal) or empty($authHeaderVal)) {
        throw new \Exception("Unable to set credentials for calling API");
      }

      $headers = [];
      $headers[self::HEADER_KEY_AUTHORIATION] = $authHeaderVal;
      $headers[self::HEADER_KEY_CONTENT_TYPE] = [self::MIME_TYPE_JSON];
      $headers[ConfigHelperContract::WAYFAIR_INTEGRATION_HEADER] = $this->configHelper->getIntegrationAgentHeader();

      $arguments = [
        $url,
        [
          'json' => [
            'query' => $query,
            'variables' => $variables
          ],
          'headers' => $headers
        ]
    ];

    // php copies arrays
    $header_for_logging = $headers;

    $needsMask = ['Authorization'];
    foreach ($needsMask as $key) {
      if (array_key_exists($key, $header_for_logging)) {
        $header_for_logging[$key] = StringHelper::mask($header_for_logging[$key]);
      }
    }

    // Array containing log relevant information
    $body_for_logging = [
      'query' => $query,
      'variables' => $variables
    ];

    $this->loggerContract
        ->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_API_SERVICE), ['additionalInfo' => [
          'URL' => $url,
          'Header' => $header_for_logging,
          'Body' => $body_for_logging
        ], 'method' => __METHOD__]);

      return $this->client->call($method, $arguments);
    } catch (\Exception $e) {
      $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_API_SERVICE_ERROR), ['additionalInfo' => ['message' => $e->getMessage()], 'method' => __METHOD__]);
    }

    return null;
  }

  /**
   * @return string
   */
  private function getUrl()
  {
    return $this->urlHelper->getUrl(URLHelperContract::URL_ID_GRAPHQL);
  }
}
