<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Dto\ShipNotice\RequestDTO;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;

/**
 * Class ASNService for sending ASN shipment notification to Wayfair API.
 *
 * @package Wayfair\Core\Api\Services
 */
class ASNService extends APIService
{
  const LOG_KEY_DEBUG_ASN_SENDING = 'debugASNSending';
  const LOG_KEY_ERROR_OCCUR_WHEN_SENDING_ASN = 'errorOccurWhenSendingASN';
  const LOG_KEY_RESPONSE_MISSING_DATA = 'asnResponseMissingData';
  const LOG_KEY_RESPONSE_MISSING_PO = 'asnResponseMissingPO';
  const LOG_KEY_RESPONSE_MISSING_SHIPPING = 'asnResponseMissingShipping';

  /**
   * Send ASN message to Wayfair API for purchase order shipment details.
   *
   * @param RequestDTO $requestDTO
   *
   * @return bool
   * @throws \Exception
   */
  public function sendASN(RequestDTO $requestDTO): bool
  {

    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    try {
      $poNumber = $requestDTO->getPoNumber();

      $query = $this->getMutationString();
      $params = ['notice' => $requestDTO->toArray()];
      $this->loggerContract
        ->info(
          TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG_ASN_SENDING),
          [
            'additionalInfo' => ['query' => $query, 'params' => json_encode($params)],
            'method' => __METHOD__
          ]
        );

      $response = $this->query($query, 'post', $params);

      if ($response->hasErrors()) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_ERROR_OCCUR_WHEN_SENDING_ASN),
            [
              'additionalInfo' => [
                'poNumber' => $poNumber,
                'error' => $response->getError()
              ],
              'method' => __METHOD__
            ]
          );

        $externalLogs->addErrorLog("Unable to send ASN: " . json_encode($response->getError()));

        return false;
      }

      $responseArray = $response->getBodyAsArray();

      $dataFromResponse = $responseArray['data'];
      if (!isset($dataFromResponse) || !is_array($dataFromResponse) || empty($dataFromResponse)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_RESPONSE_MISSING_DATA),
            [
              'additionalInfo' => [
                'poNumber' => $poNumber
              ],
              'method' => __METHOD__
            ]
          );

        $externalLogs->addErrorLog('ASN response from Wayfair does not contain a data element');

        return false;
      }

      $purchaseOrdersFromData = $dataFromResponse['purchaseOrders'];
      if (!isset($purchaseOrdersFromData) || !is_array($purchaseOrdersFromData) || empty($purchaseOrdersFromData)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_RESPONSE_MISSING_PO),
            [
              'additionalInfo' => [
                'poNumber' => $poNumber
              ],
              'method' => __METHOD__
            ]
          );

        $externalLogs->addErrorLog('ASN response from Wayfair does not contain Purchase Order data');
        return false;
      }

      $shipmentFromPurchaseOrders = $purchaseOrdersFromData['shipment'];
      if (!isset($shipmentFromPurchaseOrders) || empty($shipmentFromPurchaseOrders)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_RESPONSE_MISSING_SHIPPING),
            [
              'additionalInfo' => [
                'poNumber' => $poNumber
              ],
              'method' => __METHOD__
            ]
          );

        $externalLogs->addErrorLog('ASN response from Wayfair does not contain Shipping data');
        return false;
      }

      $externalLogs->addDebugLog("ASN response from Wayfair passed validation: " . json_encode($responseArray));

      return true;
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }

  private function getMutationString()
  {
    return 'mutation shipment($notice: ShipNoticeInput!) { '
      . '  purchaseOrders { '
      . '    shipment( '
      . '       notice: $notice, '
      . '       dryRun: ' . $this->configHelper->getDryRun() . ') { '
      . '      id, '
      . '      handle, '
      . '      status, '
      . '      submittedAt,'
      . '      completedAt '
      . '    } '
      . '  } '
      . '} ';
  }
}
