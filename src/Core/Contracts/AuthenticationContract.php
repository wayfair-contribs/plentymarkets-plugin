<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface AuthenticationContract {
  /**
   * Get an OAuth token for connections to the specified audience.
   * @see generateAuthHeader
   * @param string $audience
   * @return string
   */
  public function getOAuthToken(string $audience);

  /**
   * Refresh the OAuth token stored for connections to the specified audience
   * @param string $audience
   * @param bool $force 
   * @return void
   */
  public function refreshOAuthToken(string $audience, ?bool $force = false);

  /**
   * Get an HTTP Authorization header value for the given url
   * $param string $url
   * @return string
   */
  public function generateAuthHeader(string $url);
}
