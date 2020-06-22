<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
use Wayfair\Core\Exceptions\GraphQLQueryException;
use Wayfair\Helpers\TranslationHelper;

class FetchOrderService extends APIService {
  const FETCH_LIMIT = 50;

  /**
   * @param int $circle
   *
   * @return array
   *
   * @throws GraphQLQueryException
   * @throws \Exception
   */
  public function fetch(int $circle): array {
    $query = $this->getQuery($circle);
    try {
      $response = $this->query($query);
    } catch (\Exception $e) {
      throw new GraphQLQueryException("RequestException exception for fetching purchase orders.", $e->getMessage());
    }

    if (!isset($response))
    {
      throw new GraphQLQueryException("Did not get query response");
    }

    $body = $response->getBodyAsArray();
    if ($response->getStatusCode() != 200 || isset($body['errors']) || !isset($body['data']['purchaseOrders'])) {
      throw new \Exception("Failed to fetch purchase orders. Status code: " . $response->getStatusCode());
    }
    $result = [];
    $purchaseOrders = $body['data']['purchaseOrders'];
    foreach ($purchaseOrders as $purchaseOrder) {
      $result[] = ResponseDTO::createFromArray($purchaseOrder);
    }

    return $result;
  }

  /**
   * @param int $circle
   *
   * @return string
   */
  private function getQuery(int $circle): string {
    $dateFilter = '';
    $importOrdersSince = $this->configHelper->getImportOrderSince();
    if ($importOrdersSince) {
      $dateFilter = '  { '
        . '   field:poDate '
        . '   greaterThanOrEqualTo:"' . $importOrdersSince . '" '
        . '  } ';
    }
    $query = 'query purchaseOrders { '
      . 'purchaseOrders( '
        . 'dryRun: ' . $this->configHelper->getDryRun() . ' '
        . 'limit: ' . self::FETCH_LIMIT . ' '
        . 'offset: ' . self::FETCH_LIMIT * ($circle - 1) . ' '
        . 'filters:[ '
        . '  { '
        . '   field:open '
        . '   equals:"true" '
        . '  } '
        . $dateFilter
        . ' ] '
      . ') { '
        . 'storePrefix, '
        . 'poNumber, '
        . 'poDate, '
        . 'estimatedShipDate, '
        . 'deliveryMethodCode, '
        . 'customerName, '
        . 'customerAddress1, '
        . 'customerAddress2, '
        . 'customerCity, '
        . 'customerState, '
        . 'customerPostalCode, '
        . 'salesChannelName, '
        . 'orderType, '
        . 'packingSlipUrl, '
        . 'warehouse { '
          . 'id, '
          . 'name '
        . '}, '
        . 'products { '
          . 'partNumber, '
          . 'quantity, '
          . 'price, '
          . 'pieceCount, '
          . 'totalCost, '
          . 'name, '
          . 'weight, '
          . 'totalWeight, '
          . 'estShipDate, '
          . 'fillDate, '
          . 'sku, '
          . 'isCancelled, '
          . 'twoDayGuaranteeDeliveryDeadline, '
          . 'customComment '
        . '}, '
        . 'shipTo { '
          . 'name, '
          . 'address1, '
          . 'address2, '
          . 'city, '
          . 'state, '
          . 'country, '
          . 'postalCode, '
          . 'phoneNumber '
        . '} '
        . 'billingInfo { '
          . 'vatNumber '
        . '} '
      . '} '
    . '}';
    return $query;
  }

}
