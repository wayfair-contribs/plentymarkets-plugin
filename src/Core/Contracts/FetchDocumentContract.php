<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Contracts;

use Wayfair\Core\Dto\General\DocumentDTO;
use Wayfair\Core\Exceptions\TokenNotFoundException;

/**
 * Interface FetchShippingLabelContract
 *
 * @package Wayfair\Core\Contracts
 */
interface FetchDocumentContract {
  /**
   * @param string $url
   *
   * @return DocumentDTO
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
