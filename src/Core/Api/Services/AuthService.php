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

    if (!isset($token) || ! is_array($token) || empty($token))
    {
      $issues[] = 'Token data is empty';
    }


    if (!count($issues))
    {
      $required_elements = [self::ACCESS_TOKEN, self::STORE_TIME, self::EXPIRES_IN];
      foreach($required_elements as $elem)
      {
        if (!isset($token[$elem]) || empty($token[$elem]))
        {
          $issues[] = 'Token data is missing ' . $elem . 'element';
        }
      }
    }

    // previous check proved that the two timestamps are set
    if (!count($issues) && ($token[self::EXPIRES_IN] + $token[self::STORE_TIME]) <= time())
    {
      $issues[] = 'Token has expired';
    }

    if (count($issues))
    {
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

  /**
   * Get an OAuthToken object for use with Wayfair APIs
   *
   * @return array|null
   */
  protected function getOAuthTokenData()
  {
    $tokenModel = null;

    $credentialsChanged = $this->updateCredentials();
    if (!$credentialsChanged) {
      // can continue using current token if it didn't expire
      $tokenModel = $this->getStoredTokenData();

      if (isset($tokenModel) && $this->validateToken($tokenModel)) {
        return $tokenModel;
      }
    }

    // abandon any stored token
    $this->refreshAuth();
    return $this->getStoredTokenData();
  }

  /**
   * @return string
   * @throws TokenNotFoundException
   */
  public function generateAuthHeader()
  {
    $tokenValue = null;

    $tokenModel = $this->getOAuthTokenData();
    if (isset($tokenModel)) {
      $tokenValue = $tokenModel['access_token'];
    }

    if (!isset($tokenValue) || empty($tokenValue)) {
      throw new TokenNotFoundException("Could not get a valid OAUth token.");
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

  private function clearToken(): void
  {
    $this->saveToken([]);
  }
}
