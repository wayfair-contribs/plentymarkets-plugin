<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Core\Exceptions\AuthException;
use Wayfair\Core\Exceptions\TokenNotFoundException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\StringHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;

class AuthService implements AuthenticationContract
{
  const LOG_KEY_ATTEMPTING_AUTH = 'attemptingAuthentication';

  const HEADER_KEY_CONTENT_TYPE = "Content-Type";

  const CLIENT_ID = 'client_id';
  const CLIENT_SECRET = 'client_secret';
  const PRIVATE_INFO_KEYS = [self::CLIENT_ID, self::CLIENT_SECRET];

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
  private $clientId;

  /**
   * Secret is already exposed in Global Settings.
   * Caching it here is less of an issue.
   * @var string
   */
  private $clientSecret;

  /**
   * @var AbstractConfigHelper
   */
  private $configHelper;

  /**
   * @var LoggerContract
   */
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
    AbstractConfigHelper $configHelper,
    LoggerContract $loggerContract
  ) {
    $this->store = $storageInterfaceContract;
    $this->client = $clientInterfaceContract;
    $this->configHelper = $configHelper;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Fetch a new auth token using the wayfair auth service
   * @param string $audience
   * @return WayfairResponse
   */
  private function fetchWayfairAuthToken()
  {
    // auth URL is the same for all Wayfair audiences.
    $method = 'post';
    $audience = URLHelper::getBaseUrl();
    $clientId = $this->clientId;
    if (!isset($clientId) || empty($clientId)) {
      throw new AuthException("Unable to perform authorization: no client ID set for Wayfair");
    }

    $clientSecret = $this->clientSecret;

    if (!isset($clientSecret) || empty($clientSecret)) {
      throw new AuthException("Unable to perform authroization: no client Secret set for Wayfair");
    }

    $headersArray = [
      self::HEADER_KEY_CONTENT_TYPE => 'application/json',
      AbstractConfigHelper::WAYFAIR_INTEGRATION_HEADER => AbstractConfigHelper::INTEGRATION_AGENT_NAME
    ];

    $bodyArray =  [
      self::CLIENT_ID => $clientId,
      self::CLIENT_SECRET => $clientSecret,
      'audience' => $audience,
      'grant_type' => 'client_credentials'
    ];

    $arguments = [
      URLHelper::getAuthUrl(),
      [
        'headers' => $headersArray,
        'body' => json_encode($bodyArray)
      ]
    ];

    $this->loggerContract
      ->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_ATTEMPTING_AUTH),
        [
          'additionalInfo' =>
          [
            'audience' => $audience,
            'clientID' => $clientId,
            'maskedSecret' => StringHelper::mask($clientSecret)
          ],
          'method' => __METHOD__
        ]
      );

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
    if ($this->updateCredentials())
    {
      // token doesn't match credentials
      $token = null;
    }

    if (!isset($token) or $this->isTokenExpired()) {

      $responseObject = $this->fetchWayfairAuthToken();

      if (!isset($responseObject) || empty($responseObject)) {
        throw new AuthException("Unable to authorize user: no token data");
      }

      $responseArray = $responseObject->getBodyAsArray();

      if (!isset($responseArray) || empty($responseArray)) {
        throw new AuthException("Unable to authorize user: no token data");
      }

      if (isset($responseArray['error'])) {
        throw new AuthException("Unable to authorize user: " . $responseArray['error']);
      }
    }

    $this->saveToken($responseArray);
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

  /**
   * Sync instance credentails with global values for credentails,
   * Returning true if any changes ocurred.
   *
   * @return boolean
   */
  private function updateCredentials(): bool
  {

    $updatedClientId = $this->configHelper->getClientId();
    $updatedClientSecret = $this->configHelper->getClientSecret();

    if ($updatedClientId !== $this->clientId || $updatedClientSecret != $this->clientSecret) {
      $this->clientId = $updatedClientId;
      $this->clientSecret = $updatedClientSecret;

      return true;
    }

    return false;
  }
}
