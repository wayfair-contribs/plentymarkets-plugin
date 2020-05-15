<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Controllers;

use Wayfair\Core\Api\Services\RegisterPurchaseOrderService;
use Wayfair\Core\Dto\RegisterPurchaseOrder\RequestDTO;

/**
 * Class PurchaseRegisterController
 *
 * @package Wayfair\Controllers
 */
class PurchaseRegisterController
{

  /**
   * @param RegisterPurchaseOrderService $registerPurchaseOrderService
   *
   * @return false|string
   * @throws \Exception
   */
  public function test(RegisterPurchaseOrderService $registerPurchaseOrderService)
  {
    $requestDto = RequestDTO::createFromArray(['poNumber' => 'UK148092932']);
    $result     = $registerPurchaseOrderService->register($requestDto);

    return json_encode($result);
  }
}
