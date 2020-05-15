<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Helpers\TranslationHelper;

/**
 * Purchase order api service.
 * Class PurchaseOrderService
 *
 * @package Wayfair\Core\Api\Services
 */
class PurchaseOrderService extends APIService {
  const UNABLE_TO_GET_PURCHASE = 'unableToGetPurchaseOrderData';

  /**
   * Get purchase order and shipping information info.
   *
   * @param string $poNumber
   *
   * @return array
   * @throws \Exception
   */
  public function getPurchaseOrderInfo(string $poNumber): array
  {
    $query = 'query purchaseOrders { '
             . '   purchaseOrders(filters: [{field: poNumber, equals: "' . $poNumber . '"}], limit: 1) { '
             . '     poNumber '
             . '     poDate '
             . '     estimatedShipDate '
             . '     deliveryMethodCode '
             . '     shippingInfo { '
             . '       shipSpeed '
             . '       carrierCode '
             . '     } '
             . '     warehouse { '
             . '       id '
             . '     } '
             . '     products { '
             . '       partNumber '
             . '       quantity '
             . '       price '
             . '       pieceCount '
             . '       totalCost '
             . '       name '
             . '       weight '
             . '       totalWeight '
             . '       estShipDate '
             . '       sku '
             . '       isCancelled '
             . '       twoDayGuaranteeDeliveryDeadline '
             . '     } '
             . '   } '
             . ' } ';


    $this->loggerContract
        ->info(
            TranslationHelper::getLoggerKey('sendingPurchaseOrderQuery'), [
              'additionalInfo' => ['query' => $query],
              'method' => __METHOD__
            ]
        );

    try {
      $response     = $this->query($query);
      $responseBody = $response->getBodyAsArray();
      $this->loggerContract
          ->info(
              TranslationHelper::getLoggerKey('purchaseOrderResponseData'), [
                'additionalInfo' => ['purchaseOrder' => $responseBody],
                'method' => __METHOD__
              ]
          );
      if (isset($responseBody['errors']) || empty($responseBody['data']['purchaseOrders'][0])) {
        $this->loggerContract
            ->error(
                TranslationHelper::getLoggerKey(self::UNABLE_TO_GET_PURCHASE), [
                  'additionalInfo' => ['purchaseOrder' => $responseBody],
                  'method' => __METHOD__
                ]
            );
        throw new \Exception(TranslationHelper::getLoggerMessage(self::UNABLE_TO_GET_PURCHASE));
      }

      return $responseBody['data']['purchaseOrders'][0];
    } catch (\Exception $exception) {
      $this->loggerContract
          ->error(
              TranslationHelper::getLoggerKey(self::UNABLE_TO_GET_PURCHASE), [
                'additionalInfo' => ['message' => $exception->getMessage()],
                'method' => __METHOD__
              ]
          );
      throw $exception;
    }
  }

}
