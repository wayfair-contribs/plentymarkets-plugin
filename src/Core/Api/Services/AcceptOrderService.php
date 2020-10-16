<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Exceptions\GraphQLQueryException;
use Wayfair\Helpers\TranslationHelper;

class AcceptOrderService extends APIService
{

  /**
   * @param string $poNumber
   * @param array $items
   *
   * @return bool
   */
  public function accept(string $poNumber, array $items): bool
  {
    $query = $this->getQuery($poNumber, $items);
    try {
      $response = $this->query($query);

      if (!isset($response)) {
        throw new GraphQLQueryException("Did not get query response");
      }

      $body = $response->getBodyAsArray();
      if ($response->getStatusCode() != 200 || $response->hasErrors()) {
        // FIXME: finding status code of '0' in plentymarkets logs implying that the response is not populated
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey('acceptPurchaseOrderResponseError'),
            [
              'additionalInfo' => ['body' => $body],
              'method' => __METHOD__,
              'referenceType' => 'statusCode',
              'referenceValue' => $response->getStatusCode()
            ]
          );
        return false;
      }
      return true;

    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey('acceptPurchaseOrderError'),
          [
            'additionalInfo' => ['message' => $e->getMessage()],
            'method' => __METHOD__
          ]
        );
    }

    return false;
  }

  /**
   * @param string $poNumber
   * @param array $items
   *
   * @return string
   */
  private function getQuery(string $poNumber, array $items): string
  {
    $lineItems = '';
    foreach ($items as $key => $item) {
      $lineItems .= '{'
        . 'partNumber: "' . ((string) $item['partNumber'] ?: '') . '",'
        . 'quantity: ' . ((int) $item['quantity'] ?: 0) . ','
        . 'unitPrice: ' . ((float) $item['unitPrice'] ?: 0) . ','
        . 'estimatedShipDate: "' . ((string) $item['estimatedShipDate'] ?: '') . '" '
        . '}';
      $lineItems .= $key ? ',' : '';
    }
    $query = 'mutation accept { '
      . 'purchaseOrders { '
      . 'accept ('
      . 'poNumber: "' . $poNumber . '",'
      . 'shipSpeed: GROUND,'
      . 'dryRun: ' . $this->configHelper->getDryRun() . ','
      . 'lineItems: [' . $lineItems . ']'
      . ') { '
      . 'id'
      . '}'
      . '}'
      . '}';
    return $query;
  }
}
