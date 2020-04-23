<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api;

use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;

class APIService
{
  const LOG_KEY_API_SERVICE = 'apiService';
  const LOG_KEY_API_SERVICE_ERROR = 'apiServiceError';
  const HEADER_KEY_AUTHORIATION = 'Authorization';
  const HEADER_KEY_CONTENT_TYPE = 'Content-Type';
  const MIME_TYPE_JSON = 'application/json';

  /**
   * @var AuthenticationContract
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
   * @var ConfigHelper
   */
  protected $configHelper;

  /**
   * @param ClientInterfaceContract $clientInterfaceContract
   * @param AuthenticationContract  $authenticationContract
   * @param ConfigHelper            $configHelper
   * @param LoggerContract          $loggerContract
   */
  public function __construct(ClientInterfaceContract $clientInterfaceContract, AuthenticationContract $authenticationContract, ConfigHelper $configHelper, LoggerContract $loggerContract)
  {
    $this->client = $clientInterfaceContract;
    $this->authService = $authenticationContract;
    $this->configHelper = $configHelper;
    $this->loggerContract = $loggerContract;
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

      if (!isset($authHeaderVal) or empty($authHeaderVal))
      {
        throw new \Exception("Unable to set credentials for calling API");
      }

      $headers = [];
      $headers[self::HEADER_KEY_AUTHORIATION] = $authHeaderVal;
      $headers[self::HEADER_KEY_CONTENT_TYPE] = [self::MIME_TYPE_JSON];
      $headers[ConfigHelper::WAYFAIR_INTEGRATION_HEADER] = $this->configHelper->getIntegrationAgentHeader();

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
      $this->loggerContract
        ->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_API_SERVICE), ['additionalInfo' => [
          'url' => $url,
          'arguments' => $arguments
        ], 'method' => __METHOD__]);

      return $this->client->call($method, $arguments);
    } catch (\Exception $e) {
      $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_API_SERVICE_ERROR), ['additionalInfo' => ['message' => $e->getMessage()], 'method' => __METHOD__]);
    }
  }

  /**
   * @return string
   */
  private function getUrl()
  {
    return URLHelper::getUrl(URLHelper::URL_ID_GRAPHQL);
  }
}
