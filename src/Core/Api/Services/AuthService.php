<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Core\Exceptions\TokenNotFoundException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;

class AuthService implements AuthenticationContract {
  /**
   * @var StorageInterfaceContract
   */
  private $store;

  /**
   * @var ClientInterfaceContract
   */
  private $client;

  /**
   * @var string
   */
  private $client_id;

  /**
   * @var string
   */
  private $client_secret;

  private $loggerContract;

  /**
   * AuthService constructor.
   *
   * @param ClientInterfaceContract  $clientInterfaceContract
   * @param StorageInterfaceContract $storageInterfaceContract
   * @param AbstractConfigHelper     $abstractConfigHelper
   * @param LoggerContract           $loggerContract
   */
  public function __construct(
    ClientInterfaceContract $clientInterfaceContract,
    StorageInterfaceContract $storageInterfaceContract,
    AbstractConfigHelper $abstractConfigHelper,
    LoggerContract $loggerContract
  ) {
    $this->store = $storageInterfaceContract;
    $this->client_id = $abstractConfigHelper->getClientId();
    $this->client_secret = $abstractConfigHelper->getClientSecret();
    $this->client = $clientInterfaceContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * @return WayfairResponse
   */
  public function authenticate()
  {
    $targetURL = URLHelper::getAuthUrl();
    $method = 'post';
    $arguments = [
      $targetURL,
      [
        'headers' => [
          'Content-Type' => 'application/json',
          ConfigHelper::WAYFAIR_INTEGRATION_HEADER => ConfigHelper::INTEGRATION_AGENT_NAME
        ],
        'body' => json_encode(
                [
                  'client_id' => $this->client_id,
                  'client_secret' => $this->client_secret,
                  'audience' => URLHelper::getBaseUrl(),
                  'grant_type' => 'client_credentials'
                ]
            )
      ]
    ];
    $this->loggerContract
        ->debug(TranslationHelper::getLoggerKey('attemptingAuthentication'), ['additionalInfo' => $arguments, 'method' => __METHOD__]);

    return $this->client->call($method, $arguments);
  }

  /**
   * This refreshes the Authorization Token if it has been expired.
   *
   * @return void
   * @throws \Exception
   */
  public function refresh()
  {
    $token = $this->getToken();
    if (!isset($token) or $this->isTokenExpired()) {
      $response = $this->authenticate()->getBodyAsArray();
      if (isset($response['errors'])) {
        throw new \Exception("Unable to authenticate user: " . $response['error']);
      }
      $this->saveToken($response);
    }
  }

  /**
   * @param array $token
   *
   * @return void
   */
  public function saveToken($token)
  {
    $token['store_time'] = time();
    $this->store->set('token', json_encode($token));
  }

  /**
   * @return bool
   */
  public function isTokenExpired()
  {
    $token = $this->getToken();
    if (isset($token) && isset($token['access_token']) && isset($token['store_time'])) {
      if (($token['expires_in'] + $token['store_time']) > time()) {
        return false;
      }
    }

    return true;
  }

  /**
   * @return mixed
   */
  public function getToken()
  {
    return json_decode($this->store->get('token'), true);
  }

  /**
   * @return string
   * @throws TokenNotFoundException
   */
  public function getOAuthToken()
  {
    $token = $this->getToken();
    if (!isset($token)) {
      throw new TokenNotFoundException("Token not found.");
    }

    return 'Bearer ' . $token['access_token'];
  }
}
