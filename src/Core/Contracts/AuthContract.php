<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

use Wayfair\Core\Exceptions\AuthException;

interface AuthContract
{
  /**
   * Generate the value of an Auth header.
   * The auth token may be refreshed in order to construct the header.
   *
   * @return string
   * @throws AuthException
   */
  public function generateAuthHeader();

  /**
   * Refresh the Authorization Token, unconditionally
   *
   * @return void
   * @throws AuthException
   */
  public function refreshAuth();

  /**
   * Get an OAuth token for use with Wayfair APIs
   *
   * @return string|null
   * @throws AuthException
   */
  public function getOAuthToken();
}
