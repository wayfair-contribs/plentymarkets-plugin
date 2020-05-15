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
use Wayfair\Helpers\StringHelper;
use Wayfair\Http\WayfairResponse;

class APIService {

  const LOG_KEY_API_SERVICE = 'apiService';

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
   * @return string
   */
  public function getAuthenticationToken()
  {
    try {
      $this->authService->refresh();

      return $this->authService->getOAuthToken();
    } catch (\Exception $e) {
      $this->loggerContract
          ->error(TranslationHelper::getLoggerKey(self::LOG_KEY_API_SERVICE), ['additionalInfo' => ['message' => $e->getMessage()], 'method' => __METHOD__]);
    }
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
    $headers = [];
    $headers['Authorization'] = $this->getAuthenticationToken();
    $headers['Content-Type'] = ['application/json'];
    $headers[ConfigHelper::WAYFAIR_INTEGRATION_HEADER] = $this->configHelper->getIntegrationAgentHeader();

    $url = $this->getUrl();

    $arguments = [
      $url ,
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
        ], 'method' => __METHOD__
        ]);

    return $this->client->call($method, $arguments);
  }

  /**
   * @return string
   */
  public function getUrl()
  {
    return URLHelper::getUrl(URLHelper::URL_GRAPHQL);
  }
}
