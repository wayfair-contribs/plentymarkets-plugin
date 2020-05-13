<?php

/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Core\Contracts\URLHelperContract;
use Wayfair\Core\Exceptions\AuthenticationException;
use Wayfair\Core\Exceptions\TokenNotFoundException;
use Wayfair\Helpers\StringHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;

class AuthService implements AuthenticationContract
{
  const LOG_KEY_ATTEMPTING_AUTH = 'attemptingAuthentication';
  const LOG_KEY_DELETING_TOKEN = 'deletingToken';
  const LOG_KEY_NON_WAYFAIR = 'cannotAuthenticateNonWayfair';

  const EXPIRES_IN = 'expires_in';
  const ACCESS_TOKEN = 'access_token';
  const STORE_TIME = 'store_time';
  const TOKEN = 'token';
  const CLIENT_ID = 'client_id';
  const CLIENT_SECRET = 'client_secret';

  const PRIVATE_INFO_KEYS = [self::CLIENT_ID, self::CLIENT_SECRET];

  /**
   * stores information about authentications since boot, to avoid using tokens from before boot
   *
   * @var mixed[]
   */ 
  private static $authSinceBoot = null;

  /**
   * @var StorageInterfaceContract
   */
  private $store;

  /**
   * @var ClientInterfaceContract
   */
  private $client;

  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * @var URLHelperContract
   */
  private $urlHelperContract;

  /**
   * @var ConfigHelperContract
   */
  private $configHelperContract;

  /**
   * AuthService constructor.
   *
   * @param ClientInterfaceContract  $clientInterfaceContract
   * @param StorageInterfaceContract $storageInterfaceContract
   * @param ConfigHelperContract     $configHelperContract
   * @param LoggerContract           $loggerContract
   * @param URLHelperContract        $urlHelperContract
   */
  public function __construct(
    ClientInterfaceContract $clientInterfaceContract,
    StorageInterfaceContract $storageInterfaceContract,
    ConfigHelperContract $configHelperContract,
    LoggerContract $loggerContract,
    URLHelperContract $urlHelperContract
  ) {
    $this->store = $storageInterfaceContract;
    $this->client = $clientInterfaceContract;
    $this->loggerContract = $loggerContract;
    $this->urlHelperContract = $urlHelperContract;
    $this->configHelperContract = $configHelperContract;
  }

  /**
   * Fetch a new auth token for the given audience
   * @param string $audience
   * @return WayfairResponse
   */
  private function fetchNewToken(string $audience): WayfairResponse
  {

    $wayfairAudience = $this->urlHelperContract->getWayfairAudience($audience);

    if (!isset($wayfairAudience) || empty($wayfairAudience)) {
      $this->loggerContract
      ->warning(
        TranslationHelper::getLoggerKey(self::LOG_KEY_NON_WAYFAIR),
        [
          'additionalInfo' =>
          [
            'audience' => $audience
          ],
          'method' => __METHOD__
        ]
      );
      return null;
    }

    return $this->wayfairAuthenticate($audience);
  }

  /**
   * Fetch a new auth token using the wayfair authentication service
   * @param string $audience
   * @return WayfairResponse
   */
  private function wayfairAuthenticate(string $audience)
  {
    // auth URL is the same for all Wayfair audiences.
    $method = 'post';
    $client_id = $this->configHelperContract->getWayfairClientId();
    $client_secret = $this->configHelperContract->getWayfairClientSecret();

    $headersArray = [
      'Content-Type' => 'application/json',
      ConfigHelperContract::WAYFAIR_INTEGRATION_HEADER => ConfigHelperContract::INTEGRATION_AGENT_NAME
    ];

    $bodyArray =  [
      self::CLIENT_ID => $client_id,
      self::CLIENT_SECRET => $client_secret,
      'audience' => $audience,
      'grant_type' => 'client_credentials'
    ];

    $arguments = [
      $this->urlHelperContract->getWayfairAuthenticationUrl(),
      [
        'headers' => $headersArray,
        'body' => json_encode($bodyArray)
      ]
    ];
    
    // make sanitized versions for logging
    $maskedHeaders = $headersArray;
    $maskedBody = $bodyArray;
    $maskedArrays = [&$maskedBody, &$maskedHeaders];
    foreach (self::PRIVATE_INFO_KEYS as $pik)
    {
      foreach($maskedArrays as &$masked)
      {
        if (array_key_exists($pik, $masked))
        {
            $masked[$pik] = StringHelper::mask($masked[$pik]);
        }
      }
    }

    $this->loggerContract
      ->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_ATTEMPTING_AUTH),
        [
          'additionalInfo' =>
          [
            'headers' => $maskedHeaders,
            'body' => $maskedBody
          ],
          'method' => __METHOD__
        ]
      );

    return $this->client->call($method, $arguments);
  }

  /**
   * @param string $audience
   * @return void
   * @throws \Exception
   */
  public function refreshOAuthToken(string $audience)
  {
    $responseObject = $this->fetchNewToken($audience);

    if (!isset($responseObject) || empty($responseObject)) {
      throw new AuthenticationException("Unable to authenticate user: no token data for " . $audience);
    }

    $responseArray = $responseObject->getBodyAsArray();

    if (!isset($responseArray) || empty($responseArray)) {
      throw new AuthenticationException("Unable to authenticate user: no token data for " . $audience);
    }

    if (isset($responseArray['error'])) {
      throw new AuthenticationException("Unable to authenticate user: " . $responseArray['error']);
    }

    $this->saveToken($responseArray, $audience);
  }

  /**
   * Store token data for the given audience
   * @param array $token
   * @param string $audience
   * @return void
   */
  private function saveToken($token, $audience)
  {
    $token[self::STORE_TIME] = time();
    $key = self::getKeyForToken($audience);
    // FIXME: should be encrypted?
    $this->store->set($key, json_encode($token));
  }

  /**
   * @param string $audience
   * @return bool
   */
  public function isTokenExpired(string $audience)
  {
    $wayfairAudience = $this->urlHelperContract->getWayfairAudience($audience);

    if (isset($wayfairAudience) && !empty($wayfairAudience)) {
      $testingSetting = $this->configHelperContract->isTestingEnabled();
      if (!in_array($testingSetting, self::$authSinceBoot)) {
        // haven't used a Wayfair token for this "mode" since boot
        // so don't use token stored in the DB!

        self::$authSinceBoot[] = $testingSetting;
        return true;
      }
    }

    $token = $this->getStoredTokenModel($audience);
    return self::isTokenDataExpired($token);
  }

  /**
   * Check if the token data is missing or expired
   *
   * @param mixed $token
   * @return bool
   */
  private static function isTokenDataExpired($token)
  {
    return (!isset($token)  || empty($token)
      || !isset($token[self::ACCESS_TOKEN]) || empty($token[self::ACCESS_TOKEN])
      || !isset($token[self::STORE_TIME]) || empty($token[self::STORE_TIME])
      || !isset($token[self::EXPIRES_IN]) || empty($token[self::EXPIRES_IN])
      || ($token[self::EXPIRES_IN] + $token[self::STORE_TIME]) < time());
  }

  /**
   * Get token that's currently stored
   * @param string $audience
   * @return mixed
   */
  private function getStoredTokenModel(string $audience)
  {

    $key = self::getKeyForToken($audience);
    // FIXME: need to decrypt if we add encryption for the token
    return json_decode($this->store->get($key), true);
  }

  /**
   * @param string $url
   * @return string
   * @throws TokenNotFoundException
   */
  public function generateAuthHeader(string $url): string
  {

    $wayfairAudience = $this->urlHelperContract->getWayfairAudience($url);
    if (isset($wayfairAudience) && !empty($wayfairAudience)) {
      // we only know how to authenticate for wayfair,
      // and we should NEVER return token data when the endpoint is not at Wayfair.
      $tokenValue = $this->getOAuthToken($wayfairAudience);
      return 'Bearer ' . $tokenValue;
    }

    // no authentication information available for this URL
    return null;
  }

  /**
   * Get the stored token value for the audience
   *
   * @param string $audience
   * @return string
   * @throws TokenNotFoundException
   */
  public function getOAuthToken(string $audience)
  {
    // check for staleness and refresh
    if ($this->isTokenExpired($audience)) {
      $this->refreshOAuthToken($audience);
    }

    $tokenModel = $this->getStoredTokenModel($audience);
    if (!isset($tokenModel) || empty($tokenModel)) {
      throw new TokenNotFoundException("Token not found for " . $audience);
    }

    return $tokenModel[self::ACCESS_TOKEN];
  }

  /**
   * Get the key for storing and looking up the oauth key for the audience
   *
   * @param string $audience
   * @return string
   */
  private static function getKeyForToken(string $audience)
  {
    return self::TOKEN . '_' . base64_encode(strtolower($audience));
  }

  /**
   * Clear the token stored for the given audience
   *
   * @param string $audience
   * @return void
   */
  public function deleteOAuthToken(string $audience)
  {
    $key = self::getKeyForToken($audience);
    if (isset($key) && !empty($key)) {
      $this->loggerContract
        ->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_DELETING_TOKEN), ['audience' => $audience, 'method' => __METHOD__]);
      $this->store->remove($key);
    }
  }
}
