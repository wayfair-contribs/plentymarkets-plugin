<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Plugin\Http\Request;
use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\URLHelperContract;

/**
 * Carrier SCAC code mapping controller.
 * Class CarrierScacController
 *
 * @package Wayfair\Controllers
 */
class AuthenticationController
{
    /**
     * @var AuthenticationContract $authService
     */
    private $authService;

    /**
     * CarrierScacController constructor.
     *
     * @param AuthenticationContract $authService
     */
    public function __construct(AuthenticationContract $authService)
    {
        $this->authService = $authService;
    }

  /**
   * Reset authentications
   * (Clear authentication caches)
   *
   * @param Request $request
   *
   * @return false|string
   * @throws ValidationException
   */
    public function postResetAuthentication(Request $request)
    {
        $status = true;
        foreach (URLHelperContract::URLS_USING_WAYFAIR_AUTH as $key => $value) 
        {
            $this->authService->deleteOAuthToken($value);
        }
        
        return json_encode(['status' => true]);
    }
}
