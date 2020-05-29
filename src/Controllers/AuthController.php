<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Plugin\Http\Request;
use Wayfair\Core\Contracts\AuthContract;
use Wayfair\Core\Contracts\URLHelperContract;

/**
 * Auth Controller
 * For performing actions involving auth tokens
 *
 * @package Wayfair\Controllers
 */
class AuthController
{
    /**
     * @var AuthContract $authService
     */
    private $authService;

    /**
     * CarrierScacController constructor.
     *
     * @param AuthContract $authService
     */
    public function __construct(AuthContract $authService)
    {
        $this->authService = $authService;
    }

  /**
   * Reset authorizations
   * (Clear cached auth tokens)
   *
   * @param Request $request
   *
   * @return false|string
   * @throws ValidationException
   */
    public function postResetAuth(Request $request)
    {
        $status = true;
        foreach (URLHelperContract::URLS_USING_WAYFAIR_AUTH as $key => $value) 
        {
            $this->authService->deleteOAuthToken($value);
        }
        
        return json_encode(['status' => true]);
    }
}
