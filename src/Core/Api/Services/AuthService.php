<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Core\Exceptions\AuthenticationException;
use Wayfair\Core\Exceptions\TokenNotFoundException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;

class AuthService implements AuthenticationContract {

  const EXPIRES_IN = 'expires_in';
  const ACCESS_TOKEN = 'access_token';
  const STORE_TIME = 'store_time';
  const TOKEN = 'token';

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
   * Fetch a new auth token for the given audience
   * @param string $audience
   * @return WayfairResponse
   */
  private function fetchNewToken(string $audience): ?WayfairResponse {

    $wayfairAudience = URLHelper::getWayfairAudience($audience);

    if (!isset($wayfairAudience) || empty($wayfairAudience))
    {
      // TODO: log about this - we only handle wayfair API authentication
      return null;
    }

    return $this->wayfairAuthenticate($audience);
  }

  /**
   * Fetch a new auth token using the wayfair authentication service
   * @param string $audience
   * @return WayfairresponseArray
   */
  private function wayfairAuthenticate(string $audience) {
    // auth URL is the same for all Wayfair audiences.
    $method = 'post';
    $arguments = [
        URLHelper::getAuthUrl(),
        [
            'headers' => [
                'Content-Type' => 'application/json',
                ConfigHelper::WAYFAIR_INTEGRATION_HEADER => ConfigHelper::INTEGRATION_AGENT_NAME
            ],
            'body' => json_encode(
                [
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'audience' => $audience,
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
   * @param string $audience
   * @param bool $force
   * @return void
   * @throws \Exception
   */
  public function refreshOAuthToken(string $audience, ?bool $force = false) {
    $token = $this->getStoredTokenModel($audience);
    // TODO: logging around token being set or expired
    if ($force || !isset($token) || $this->isTokenExpired($audience)) {

      $responseObject = $this->fetchNewToken($audience);

      if (!isset($responseObject) || empty($responseObject))
      {
        throw new AuthenticationException("Unable to authenticate user: no token data for " . $audience);
      }

      $responseArray = $responseObject->getBodyAsArray();
      
      if (!isset($responseArray) || empty($responseArray))
      {
        throw new AuthenticationException("Unable to authenticate user: no token data for " . $audience);
      }

      if (isset($responseArray['errors'])) {
        throw new AuthenticationException("Unable to authenticate user: " . $responseArray['error']);
      }

      $this->saveToken($responseArray, $audience);
    }
  }

  /**
   * Store token data for the given audience
   * @param array $token
   * @param string $audience
   * @return void
   */
  private function saveToken($token, $audience) {
    $token[self::STORE_TIME] = time();
    $key = self::getKeyForToken($audience);
    // FIXME: should be encrypted?
    $this->store->set($key, json_encode($token));
  }

  /**
   * @param string $audience
   * @return bool
   */
  public function isTokenExpired(string $audience) {
    $token = $this->getStoredTokenModel($audience);
    if (isset($token) && isset($token[self::ACCESS_TOKEN]) && isset($token[self::STORE_TIME]) && isset($token[self::EXPIRES_IN])) {
      if (($token[self::EXPIRES_IN] + $token[self::STORE_TIME]) > time()) {
        return false;
      }
    }

    return true;
  }

  /**
   * Get token that's currently stored
   * @param string $audience
   * @return mixed
   */
  private function getStoredTokenModel(string $audience) {
    
    $key = self::getKeyForToken($audience);
    // FIXME: need to decrypt if we add encryption for the token
    return json_decode($this->store->get($key), true);
  }

  /**
   * @param string $url
   * @return string
   * @throws TokenNotFoundException
   */
  public function generateAuthHeader(string $url): ?string 
  {

    $wayfairAudience = URLHelper::getWayfairAudience($url);
    if (isset($wayfairAudience) && !empty($wayfairAudience)) 
    {
      // we only know how to authenticate for wayfair,
      // and we should NEVER return token data when the endpoint is not at Wayfair.
      $tokenValue = $this->getOAuthToken($wayfairAudience);
      return 'Bearer ' . $tokenValue;
    }

    // no authentication information available for this URL
    return null;
  }

  /**
   * Get the store token value for the audience
   *
   * @param string $audience
   * @return void
   */
  public function getOAuthToken(string $audience) {
    // check for staleness and refresh
    $this->refreshOAuthToken($audience);
    $tokenModel = $this->getStoredTokenModel($audience);
    if (!isset($tokenModel)) {
      throw new TokenNotFoundException("Token not found for " . $audience);
    }

    return $tokenModel[self::ACCESS_TOKEN];
  }

  /**
   * Get the key for storing and looking up the oauth key for the audience
   *
   * @param string $audience
   * @return void
   */
  private static function getKeyForToken(string $audience)
  {
    return self::TOKEN . '_' . base64_encode(strtolower($audience));
  }
}
