<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface AuthContract {
  /**
   * Generate an Authorization header value for contacting the Wayfair APIs
   * @return string
   */
  public function generateAuthHeader();

  /**
   * Force a refresh of the Authorization information
   * @return void
   */
  public function refresh();
}
