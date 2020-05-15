<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;

class PendingPurchaseOrderMapper
{

  /**
   * @param ResponseDTO $dto
   *
   * @return array
   */
  public function map(ResponseDTO $dto): array
  {
    $items = [];
    $estimatedShipDate = $dto->getEstimatedShipDate() ?: date('Y-m-d H:i:s.u P');
    foreach ($dto->getProducts() as $product) {
      $items[] = [
        'partNumber' => $product->getPartNumber(),
        'quantity' => $product->getQuantity(),
        'unitPrice' => $product->getPrice(),
        'estimatedShipDate' => $estimatedShipDate
      ];
    }
    return [
      'poNum' => $dto->getPoNumber(),
      'items' => $items
    ];
  }
}
