<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
use Wayfair\Core\Exceptions\GraphQLQueryException;
use Wayfair\Helpers\TranslationHelper;

class FetchOrderService extends APIService
{
  const FETCH_LIMIT = 50;

  const LOG_KEY_INCOMING_PO = 'incomingPurchaseOrder';

  /**
   * @param int $circle
   *
   * @return array
   *
   * @throws GraphQLQueryException
   * @throws \Exception
   */
  public function fetch(int $circle): array
  {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    $query = $this->getQuery($circle);

    $response = $this->query($query);

    if (!isset($response)) {
      throw new GraphQLQueryException("Did not get query response");
    }

    $body = $response->getBodyAsArray();
    $errors = $response->getError();
    if ($response->getStatusCode() != 200 || (isset($errors) && !empty($errors)) || !isset($body['data']['purchaseOrders'])) {
      $message = 'Failed to fetch purchase orders. Status code: ' . $response->getStatusCode();

      if (isset($errors) && !empty($errors)) {
        $message .= ' Errors: ' . json_encode($errors);
      }

      throw new \Exception($message);
    }

    $result = [];
    $purchaseOrders = $body['data']['purchaseOrders'];
    foreach ($purchaseOrders as $purchaseOrder) {
      $loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INCOMING_PO),
        [
          'additionalInfo' => [
            'PO' => $purchaseOrder
          ],
          'method' => __METHOD__
        ]
      );

      $result[] = ResponseDTO::createFromArray($purchaseOrder);
    }

    return $result;
  }

  /**
   * @param int $circle
   *
   * @return string
   */
  private function getQuery(int $circle): string
  {
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
      // TODO: include 'isCancelled' after it stops causing issues (2020-Jul-07)
      // . 'isCancelled, '
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
