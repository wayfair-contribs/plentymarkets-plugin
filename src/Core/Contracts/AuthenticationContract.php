<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

interface AuthenticationContract {
  /**
   * @return string
   */
  public function getOAuthToken();

  /**
   * @return void
   */
  public function refresh();

  /**
   * @return string
   */
  public function generateOAuthHeader();
}
