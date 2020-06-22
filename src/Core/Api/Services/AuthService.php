<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Contracts\AuthContract;
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

class AuthService implements AuthContract
{
  const STORAGE_KEY_TOKEN = 'token';

  const LOG_KEY_ATTEMPTING_AUTH = 'attemptingAuthentication';

  const HEADER_KEY_CONTENT_TYPE = "Content-Type";

  const EXPIRES_IN = 'expires_in';
  const ACCESS_TOKEN = 'access_token';
  const STORE_TIME = 'store_time';
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

    $targetURL = URLHelper::getAuthUrl();
    $bodyJson = json_encode($bodyArray);

    $arguments = [
      $targetURL,
      [
        'headers' => $headersArray,
        'body' => $bodyJson
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
   * Refresh the Authorization Token, unconditionally
   *
   * @return void
   * @throws \Exception
   */
  public function refreshAuth()
  {
    $this->clearToken();

    $responseObject = $this->fetchWayfairAuthToken();

    if (!isset($responseObject) || empty($responseObject)) {
      throw new AuthException("Unable to authorize user: no token data in response from Wayfair");
    }

    $responseArray = $responseObject->getBodyAsArray();

    if (!isset($responseArray) || empty($responseArray)) {
      throw new AuthException("Unable to authorize user: no token data in response from Wayfair");
    }

    if (isset($responseArray['error'])) {
      throw new AuthException("Unable to authorize user: " . $responseArray['error']);
    }

    $this->saveToken($responseArray);
  }

  /**
   * @param array $token
   *
   * @return void
   */
  private function saveToken($token)
  {
    $token[self::STORE_TIME] = time();
    $this->store->set(self::STORAGE_KEY_TOKEN, json_encode($token));
  }

  /**
   * Check if a token model is valid for use.
   * A token that passes validation has all required fields and has not yet expired.
   *
   * @return boolean
   */
  private static function validateToken($token): bool
  {
    return isset($token) && !empty($token)
      && isset($token[self::ACCESS_TOKEN]) && !empty($token[self::ACCESS_TOKEN])
      && isset($token[self::STORE_TIME]) && !empty($token[self::STORE_TIME])
      && isset($token[self::EXPIRES_IN]) && empty($token[self::EXPIRES_IN])
      && ($token[self::EXPIRES_IN] + $token[self::STORE_TIME]) > time();
  }

  /**
   * @return mixed
   */
  private function getStoredToken()
  {
    return json_decode($this->store->get(self::STORAGE_KEY_TOKEN), true);
  }

  /**
   * Get an OAuthToken object for use with Wayfair APIs
   *
   * @return string
   */
  public function getOAuthToken()
  {
    $token = null;
    if (!$this->updateCredentials()) {
      // no changes to credentials - token is okay, but could be expired
      $token = $this->getStoredToken();
    }

    if (!isset($token) || !self::validateToken($token)) {
      $this->refreshAuth();
    }

    return $this->getStoredToken();
  }

  /**
   * @return string
   * @throws TokenNotFoundException
   */
  public function generateAuthHeader()
  {
    $tokenValue = null;

    $tokenModel = $this->getOAuthToken();
    if (isset($tokenModel)) {
      $tokenValue = $tokenModel['access_token'];
    }

    if (!isset($tokenValue) || empty($tokenValue)) {
      throw new TokenNotFoundException("Could not get a valid OAUth token.");
    }

    return 'Bearer ' . $tokenValue;
  }

  /**
   * Sync instance credentails with global values for credentails.
   * If changes are detected, the stored token is cleared and the funciton returns true
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

      $this->clearToken();
      return true;
    }

    return false;
  }

  private function clearToken(): void
  {
    $this->saveToken([]);
  }
}
