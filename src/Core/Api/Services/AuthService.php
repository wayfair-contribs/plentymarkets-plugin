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
  const LOG_KEY_NEW_TOKEN = 'gotNewAuthToken';
  const LOG_KEY_INVALID_TOKEN = 'authTokenIsInvalid';

  const HEADER_KEY_CONTENT_TYPE = "Content-Type";

  const EXPIRES_IN = 'expires_in';
  const ACCESS_TOKEN = 'access_token';
  const STORE_TIME = 'store_time';
  const CLIENT_ID = 'client_id';
  const CLIENT_SECRET = 'client_secret';
  const PRIVATE_INFO_KEYS = [self::CLIENT_ID, self::CLIENT_SECRET];

  /** Accounts for the time it takes to download the token and save it (in seconds) */
  const TOKEN_EXPIRY_CUSHION = 5;

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
      throw new AuthException("Unable to perform authorization: no client Secret set for Wayfair");
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

    // we should be adding the timestamp ASAP so that the time is accurate.
    // steps such as validation may cause time skew.
    $tokenArray = self::addTimestampToToken($responseArray);

    if (!$this->validateToken($tokenArray)) {
      throw new AuthException("Did not receive valid auth token data");
    }

    // save once it has a timestamp and it has been validated
    try {
      $tokenArray = $this->saveToken($responseArray);
    } catch (\Exception $e) {
      throw new AuthException("Unable to save Auth Token", 0, $e);
    }

    $this->loggerContract
      ->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_NEW_TOKEN),
        [
          'additionalInfo' =>
          [
            'maskedToken' => StringHelper::mask($tokenArray[self::ACCESS_TOKEN])
          ],
          'method' => __METHOD__
        ]
      );
  }

  /**
   * Set the token's timestamp and return it
   * @param array $tokenModel
   *
   * @return array
   */
  private static function addTimestampToToken($tokenModel)
  {
    $tokenModel[self::STORE_TIME] = time();
    return $tokenModel;
  }

  /**
   * Save a token
   * @param array $tokenModel
   *
   * @return void
   */
  private function saveToken($tokenModel)
  {
    $this->store->set(self::STORAGE_KEY_TOKEN, json_encode($tokenModel));
  }

  /**
   * Check if a token model is valid for use.
   * A token that passes validation has all required fields and has not yet expired.
   *
   * @param array $token
   *
   * @return boolean
   */
  private function validateToken($token): bool
  {
    $issues = [];

    if (!isset($token) || !is_array($token) || empty($token)) {
      $issues[] = 'Token data is empty';
    }


    if (!count($issues)) {
      $required_elements = [self::ACCESS_TOKEN, self::STORE_TIME, self::EXPIRES_IN];
      foreach ($required_elements as $elem) {
        if (!isset($token[$elem]) || empty($token[$elem])) {
          $issues[] = 'Token data is missing ' . $elem . 'element';
        }
      }
    }

    // previous check proved that the two timestamps are set.
    if (!count($issues) && ($token[self::STORE_TIME] + $token[self::EXPIRES_IN] - self::TOKEN_EXPIRY_CUSHION) <= time()) {
      $issues[] = 'Token has expired';
    }

    if (count($issues)) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_TOKEN),
          [
            'additionalInfo' =>
            [
              'issues' => $issues
            ],
            'method' => __METHOD__
          ]
        );
      return false;
    }

    return true;
  }

  /**
   * Load the stored token data
   * @return array|null
   */
  private function getStoredTokenData()
  {
    return json_decode($this->store->get(self::STORAGE_KEY_TOKEN), true);
  }


  public function getOAuthToken()
  {
    $credentialsChanged = $this->updateCredentials();
    if (!$credentialsChanged) {
      // no change in credentials - stored token is okay if not expired
      $oldTokenData = $this->getStoredTokenData();

      if (isset($oldTokenData) && $this->validateToken($oldTokenData)) {
        // token found, and it has not expired. use it.
        return $oldTokenData[self::ACCESS_TOKEN];
      }
    }

    // abandon any stored token
    $this->refreshAuth();
    $refreshedTokenData = $this->getStoredTokenData();
    if (isset($refreshedTokenData) && is_array($refreshedTokenData)) {
      return $refreshedTokenData[self::ACCESS_TOKEN];
    }

    // even after refreshing, we don't have a good token.
    return null;
  }


  public function generateAuthHeader()
  {
    $tokenValue = $this->getOAuthToken();

    if (!isset($tokenValue) || empty($tokenValue)) {
      throw new TokenNotFoundException("Could not get a valid OAuth token.");
    }

    return 'Bearer ' . $tokenValue;
  }

  /**
   * Sync instance credentials with global values for credentials.
   * If changes are detected, the stored token is cleared and the function returns true
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

  /**
   * Clear the auth token data in memory
   *
   * @return void
   */
  private function clearToken(): void
  {
    // storage contract has no remove
    $this->saveToken([]);
  }
}
