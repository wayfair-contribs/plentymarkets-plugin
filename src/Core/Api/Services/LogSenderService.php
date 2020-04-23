<?php
/**
 * NOTE: This file is not extended from APIService to prevent Circular Dependency.
 *
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;

class LogSenderService {

  /**
   * @var ClientInterfaceContract
   */
  private $client;

  /**
   * @var AuthenticationContract
   */
  private $authService;

  /**
   * @var ConfigHelper
   */
  private $configHelper;

  public function __construct(ClientInterfaceContract $clientInterfaceContract, AuthenticationContract $authenticationContract, ConfigHelper $configHelper) {
    $this->client = $clientInterfaceContract;
    $this->authService = $authenticationContract;
    $this->configHelper = $configHelper;
  }

  public function execute(array $logs) {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    $declareVariables = '';
    $useVariables = '';
    $variables = [];
    try {
      foreach ($logs as $key => $log) {
        if ($key) {
          $declareVariables .= ', ';
          $useVariables     .= ', ';
        }
        $declareVariables            .= '$l' . ($key + 1) . ': ExternalLogInput!';
        $useVariables                .= 'log' . ($key + 1) . ': log(log: $l' . ($key + 1) . ')';
        $variables['l' . ($key + 1)] = [
            'app'     => ConfigHelper::INTEGRATION_AGENT_NAME,
            'level'   => $log['level'],
            'message' => $log['message'],
            'details' => $log['details'],
            'type'    => $log['logType'] ?: 'OTHER'
        ];
        if ($log['metrics']) {
          $variables['l' . ($key + 1)]['metrics'] = $log['metrics'];
        }
      }
      $query = 'mutation logExternalMessage(' . $declareVariables . ') {'
               . 'externalLog {'
               . $useVariables
               . '}'
               . '}';
      $this->query($query, 'post', $variables);
    }
    catch (\Exception $e)
    {
      // don't let Exceptions leak out of this sender,
      // as they may override more important Exceptions.
      $loggerContract->error(TranslationHelper::getLoggerKey('unableToSendLogsToWayfair'), [
          'additionalInfo' => ['message' => $e->getMessage()],
          'method'         => __METHOD__
      ]);
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
  public function query($query, $method = 'post', $variables = []) {
    // TODO: merge logic with APIService?
    $url = URLHelper::getUrl(URLHelper::URL_ID_GRAPHQL);
    $authHeaderVal = $this->authService->generateAuthHeader($url);
    
    if (!isset($authHeaderVal) or empty($authHeaderVal))
    {
      throw new \Exception("Unable to set credentials for calling Wayfair API");
    }

    $headers = [];
    $headers['Authorization'] = $authHeaderVal;
    $headers['Content-Type'] = ['application/json'];
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
    return $this->client->call($method, $arguments);
  }
}
