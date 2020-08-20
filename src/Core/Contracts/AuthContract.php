<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface AuthContract
{
  /**
   * Generate an Authorization header value for contacting the Wayfair APIs
   * @return string
   */
  public function generateAuthHeader();

  /**
   * Force a refresh of the Authorization information
   * @return void
   */
  public function refreshAuth();

  /**
   * Get an OAuth token for use with Wayfair APIs
   *
   * @return string|null
   */
  public function getOAuthToken();
}
