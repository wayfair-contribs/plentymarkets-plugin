<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

use Wayfair\Core\Dto\ShippingLabel\ResponseDTO;
use Wayfair\Core\Exceptions\TokenNotFoundException;

/**
 * Interface FetchShippingLabelContract
 *
 * @package Wayfair\Core\Contracts
 */
interface FetchDocumentContract {
  /**
   * Fetch a document from the document service at the specified URL
   * @param string $url
   *
   * @return ResponseDTO
   * @throws TokenNotFoundException
   */
  public function fetch(string $url);

  /**
   * Get tracking numbers for purchase order.
   * Param PoNumber must be without prefix (first two characters).
   *
   * TODO: move to a different module (and pluralize function name) as tracking numbers are not documents.
   *
   * @param int $poNumber
   *
   * @return mixed
   */
  public function getTrackingNumber(int $poNumber);
}
