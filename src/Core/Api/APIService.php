<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api;

use Wayfair\Core\Contracts\AuthContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\AuthException;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Helpers\StringHelper;
use Wayfair\Http\WayfairResponse;

/**
 * Base class for modules that call Wayfair's APIs
 *
 * WARNING: to avoid circular dependencies, do NOT use LogSenderService in this module
 */
class APIService
{

  const LOG_KEY_API_SERVICE = 'apiService';
  const LOG_KEY_AUTH_FAILURE = 'authFailure';
  const LOG_KEY_AUTH_RETRY = 'retryingAuth';

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
   * @var ConfigHelper
   */
  protected $configHelper;

  /**
   * @param ClientInterfaceContract $clientInterfaceContract
   * @param AuthContract            $authContract
   * @param ConfigHelper            $configHelper
   * @param LoggerContract          $loggerContract
   */
  public function __construct(ClientInterfaceContract $clientInterfaceContract, AuthContract $authContract, ConfigHelper $configHelper, LoggerContract $loggerContract)
  {
    $this->client = $clientInterfaceContract;
    $this->authService = $authContract;
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
    return $this->queryInternal($query, $method, $variables);
  }

  /**
   * Perform the query, retrying if the allowance is more than 0
   * @param string $query
   * @param string $method
   * @param array  $variables
   * @param int $retryRemaining
   *
   * @throws \Exception
   * @return WayfairResponse
   */
  private function queryInternal($query, $method = 'post', $variables = [], $retryRemaining = 1)
  {
    try {
      $headers = [];
      // currently, all requests to go to Wayfair endpoints that require authorization
      $headers['Authorization'] = $this->authService->generateAuthHeader();
      $headers['Content-Type'] = ['application/json'];
      $headers[ConfigHelper::WAYFAIR_INTEGRATION_HEADER] = $this->configHelper->getIntegrationAgentHeader();

      $url = $this->getUrl();

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

      $response =  $this->client->call($method, $arguments);
      $responseCode = 0;
      if (isset($response)) {
        $responseCode = $response->getStatusCode();
      }

      if (400 <= $responseCode < 500) {
        throw new AuthException("Response code indicates auth issue", $response->getStatusCode());
      }

      return $response;
    } catch (AuthException $ae) {

      $this->loggerContract
        ->error(TranslationHelper::getLoggerKey(self::LOG_KEY_AUTH_FAILURE), ['additionalInfo' => ['message' => $ae->getMessage()], 'method' => __METHOD__]);

      if ($retryRemaining > 0) {
        // recursive call with new auth token
        $this->loggerContract
          ->info(TranslationHelper::getLoggerKey(self::LOG_KEY_AUTH_RETRY), ['additionalInfo' => ['message' => $ae->getMessage()], 'method' => __METHOD__]);

        // let refreshAuth throw its Exceptions
        $this->authService->refreshAuth();
        return $this->queryInternal($query, $method, $variables, $retryRemaining - 1);
      } else {
        // no retries left - give up
        throw $ae;
      }
    }
  }

  /**
   * @return string
   */
  public function getUrl()
  {
    return URLHelper::getUrl(URLHelper::URL_GRAPHQL);
  }
}
