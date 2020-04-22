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

class APIService {

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
  public function __construct(ClientInterfaceContract $clientInterfaceContract, AuthenticationContract $authenticationContract, ConfigHelper $configHelper, LoggerContract $loggerContract) {
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
  public function query($query, $method = 'post', $variables = []) {
    $url = $this->getUrl();
    $audience = URLHelper::getWayfairAudience($url);
    $this->authService->refresh($audience);
    $authHeaderVal = $this->authService->generateOAuthHeader($audience);
    
    $headers = [];
    $headers['Authorization'] = $authHeaderVal;
    $headers['Content-Type'] = ['application/json'];
    $headers[ConfigHelper::WAYFAIR_INTEGRATION_HEADER] = $this->configHelper->getIntegrationAgentHeader();

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
    $this->loggerContract
        ->debug(TranslationHelper::getLoggerKey('apiService'), ['additionalInfo' => [
          'url' => $url,
          'arguments' => $arguments
        ], 'method' => __METHOD__]);

    return $this->client->call($method, $arguments);
  }

  /**
   * @return string
   */
  private function getUrl() {
    return URLHelper::getUrl(URLHelper::URL_ID_GRAPHQL);
  }
}
