<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api;

use Wayfair\Core\Contracts\AuthContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\AuthException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Factories\ExternalLogsFactory;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Helpers\StringHelper;
use Wayfair\Http\WayfairResponse;

class APIService
{

  const LOG_KEY_API_SERVICE = 'apiService';
  const LOG_KEY_AUTH_FAILURE = 'authFailure';

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
   * @var AbstractConfigHelper
   */
  protected $configHelper;

  /**
   * @var LogSenderService
   */
  protected $logSenderService;

  /**
   * @var ExternalLogsFactory
   */
  protected $externalLogsFactory;

  /**
   * @param ClientInterfaceContract $clientInterfaceContract
   * @param AuthContract            $authContract
   * @param AbstractConfigHelper    $configHelper
   * @param LoggerContract          $loggerContract
   */
  public function __construct(
    ClientInterfaceContract $clientInterfaceContract,
    AuthContract $authContract,
    AbstractConfigHelper $configHelper,
    LoggerContract $loggerContract,
    LogSenderService $logSenderService,
    ExternalLogsFactory $externalLogsFactory
  ) {
    $this->client = $clientInterfaceContract;
    $this->authService = $authContract;
    $this->configHelper = $configHelper;
    $this->loggerContract = $loggerContract;
    $this->logSenderService = $logSenderService;
    $this->externalLogsFactory = $externalLogsFactory;
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
      $headers = [];
      // currently, all requests to go to Wayfair endpoints that require authorization
      $headers['Authorization'] = $this->authService->generateAuthHeader();
      $headers['Content-Type'] = ['application/json'];
      $headers[AbstractConfigHelper::WAYFAIR_INTEGRATION_HEADER] = $this->configHelper->getIntegrationAgentHeader();

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

      return $this->client->call($method, $arguments);
    } catch (AuthException $ae) {
      $this->loggerContract
        ->error(TranslationHelper::getLoggerKey(self::LOG_KEY_AUTH_FAILURE), ['additionalInfo' => ['message' => $ae->getMessage()], 'method' => __METHOD__]);
      throw $ae;
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
