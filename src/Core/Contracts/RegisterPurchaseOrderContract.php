<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

use Wayfair\Core\Dto\RegisterPurchaseOrder\RequestDTO;
use Wayfair\Core\Dto\RegisterPurchaseOrder\ResponseDTO;

interface RegisterPurchaseOrderContract {

  /**
   * Register for a purchase order shipping label.
   *
   * @param RequestDTO $requestDTO
   *
   * @return ResponseDTO
   */
  public function register(RequestDTO $requestDTO): ResponseDTO;
}
