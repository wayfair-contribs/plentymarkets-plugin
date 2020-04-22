<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface AuthenticationContract {
  /**
   * Get an OAuth token for connections to the specified audience
   * @param string $audience
   * @return string
   */
  public function getOAuthToken(string $audience);

  /**
   * Refresh the OAuth token stored for connections to the specified audience
   * @param string $audience
   * @return void
   */
  public function refresh(string $audience);

  /**
   * Get an OAuth token bearer header value for the given audience
   * $param string $audience
   * @return string
   */
  public function generateOAuthHeader(string $audience);
}
