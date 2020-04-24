<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\URLHelperContract;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\PendingOrdersRepository;

class AcceptOrderService extends APIService
{

  /**
   * @var PendingOrdersRepository
   */
  private $pendingOrdersRepository;

  /**
   * FetchOrderService constructor.
   *
   * @param ClientInterfaceContract $clientInterfaceContract
   * @param AuthenticationContract $authenticationContract
   * @param ConfigHelper $configHelper
   * @param PendingOrdersRepository $pendingOrdersRepository
   * @param LoggerContract $loggerContract
   * @param URLHelperContract $urlHelperContract;
   */
  public function __construct(
    ClientInterfaceContract $clientInterfaceContract,
    AuthenticationContract $authenticationContract,
    ConfigHelper $configHelper,
    PendingOrdersRepository $pendingOrdersRepository,
    LoggerContract $loggerContract,
    URLHelperContract $urlHelperContract
  )
  {
    parent::__construct($clientInterfaceContract, $authenticationContract, $configHelper, $loggerContract, $urlHelperContract);
    $this->pendingOrdersRepository = $pendingOrdersRepository;
  }

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
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey('acceptPurchaseOrderError'), [
            'additionalInfo' => ['message' => $e->getMessage()],
            'method' => __METHOD__
          ]
        );
      return false;
    }
    $body = $response->getBodyAsArray();
    if ($response->getStatusCode() != 200 || $response->hasErrors()) {
      // FIXME: finding status code of '0' in plentymarkets logs implying that the response is not populated
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey('acceptPurchaseOrderResponseError'), [
            'additionalInfo' => ['body' => $body],
            'method' => __METHOD__,
            'referenceType' => 'statusCode',
            'referenceValue' => $response->getStatusCode()
          ]
        );
      return false;
    }
    return true;
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
        . 'partNumber: "' . ((string)$item['partNumber'] ?: '') . '",'
        . 'quantity: ' . ((int)$item['quantity'] ?: 0) . ','
        . 'unitPrice: ' . ((float)$item['unitPrice'] ?: 0) . ','
        . 'estimatedShipDate: "' . ((string)$item['estimatedShipDate'] ?: '') . '" '
        . '}';
      $lineItems .= $key ? ',' : '';
    }
    $query = 'mutation accept { '
      . 'purchaseOrders { '
      . 'accept ('
      . 'poNumber: "' . $poNumber . '",'
      . 'shipSpeed: GROUND,'
      . 'lineItems: [' . $lineItems . ']'
      . ') { '
      . 'id'
      . '}'
      . '}'
      . '}';
    return $query;
  }
}