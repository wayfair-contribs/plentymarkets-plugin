<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface AuthContract {
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
   * @return void
   */
  public function refreshOAuthToken(string $audience);

  /**
   * Get an HTTP Authorization header value for the given url
   * @param string $url
   * @return string
   */
  public function generateAuthHeader(string $url);

   /**
   * Clear the token stored for the given audience
   *
   * @param string $audience
   * @return void
   */
  public function deleteOAuthToken(string $audience);
}
