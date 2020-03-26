<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Contracts\FetchDocumentContract;
use Wayfair\Core\Dto\ShippingLabel\ResponseDTO;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;

/**
 * Class FetchShippingLabelService
 *
 * @package Wayfair\Core\Api\Services
 */
class FetchDocumentService extends APIService implements FetchDocumentContract
{
  const LOG_KEY_OBTAINING_TRACKING_NUMBER = 'obtainingTrackingNumber';
  const LOG_KEY_TRACKING_RESPONSE = 'trackingNumberServiceResponse';

  /**
   * Fetch shipping label file from WF server and put it in a ResponseDTO object.
   *
   * @param string $url
   *
   * @return ResponseDTO
   * @throws \Exception
   */
  public function fetch(string $url): ResponseDTO
  {
    // FIXME: this should be using a generic client via an interface, not cURL!
    $this->loggerContract
      ->debug(TranslationHelper::getLoggerKey('fetchingShipmentForURL'), ['additionalInfo' => ['url' => $url], 'method' => __METHOD__]);
    $ch = curl_init();
    try {
      if (strpos($url, URLHelper::BASE_URL) !== false) {
        // Check if token has already been expired and refresh it.
        $this->authService->refresh();
        curl_setopt(
          $ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->authService->getOAuthToken(),
            ConfigHelper::WAYFAIR_INTEGRATION_HEADER . ': ' . $this->configHelper->getIntegrationAgentHeader()
          ]
        );
      }
      // FIXME: set timeout(s) - we have seen this timeout after 10 seconds, leading to errors.
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);
      $output = curl_exec($ch);
      if (curl_errno($ch)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey('cannotCallWayfairAPI'), [
              'additionalInfo' => ['url' => $url, 'accessToken' => $this->authService->getOAuthToken()],
              'method' => __METHOD__
            ]
          );
        throw new \Exception('Unable to fetch document: ' . curl_error($ch));
      }
      return ResponseDTO::createFromArray(['fileContent' => $output]);
    } finally {
      curl_close($ch);
    }
  }

  /**
   * Get tracking number for a purchase order.
   *
   * TODO: move to a different module, as tracking numbers are not documents.
   *
   * @param int $poNumber
   *
   * @return mixed
   * @throws \Exception
   */
  public function getTrackingNumber(int $poNumber)
  {
    $query = $this->getTrackingNumberQuery($poNumber);

    $this->loggerContract
      ->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_OBTAINING_TRACKING_NUMBER), [
          'additionalInfo' => [
            'poNumber' => $poNumber,
            'query' => $query],
          'method' => __METHOD__
        ]
      );

    $responseBody = [];
    try {
      $response = $this->query($query);
      $responseBody = $response->getBodyAsArray();

      $this->loggerContract
        ->info(
          TranslationHelper::getLoggerKey(self::LOG_KEY_TRACKING_RESPONSE), [
            'additionalInfo' => ['responseBody' => $responseBody],
            'method' => __METHOD__
          ]
        );

      if ($response->hasErrors())
      {
        throw new \Exception("Errors received from tracking number service: "
          . json_encode($response->getError()));
      }

      $dataElement = $responseBody['data'];
      if (!isset($dataElement) || empty($dataElement))
      {
        throw new \Exception("No data element in tracking number response from Wayfair");
      }

      $labelGenerationEvents = $dataElement['labelGenerationEvents'];

      if (!isset($labelGenerationEvents) || empty($labelGenerationEvents))
      {
        throw new \Exception("No label generation events in tracking number response from Wayfair");
      }

      // FIXME: this does NOT return tracking numbers!
      // it returns an array of arrays.
      // the arrays mirror WF\API\GraphQL\Schema\Object\Purchase_Order\Label_Generation_Event_Type objects
      // but one would expect the values to be simple Tracking number(s), based on the name and inputs

      return $labelGenerationEvents;
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey('unableToGetTrackingNumber'), [
            'additionalInfo' => [
              'message' => $e->getMessage(),
              'responseBody' => $responseBody,
              'poNumber' => $poNumber
            ],
            'method' => __METHOD__,
            'referenceType' => 'poNumber',
            'referenceValue' => $poNumber
          ]
        );
      throw $e;
    }
  }

  /**
   * Get the query text for fetching a tracking number
   * @param string $poNumber
   * @return string
   */
  private function getTrackingNumberQuery(string $poNumber): string
  {
    return 'query labelGenerationEvents { '
      . ' labelGenerationEvents( '
      . ' filters:[{field:poNumber, equals:"' . $poNumber . '"}] '
      . ' ) { '
      . ' generatedShippingLabels{ '
      . '  numberOfLabels '
      . '  trackingNumber '
      . '  carrierCode '
      . '  carrier '
      . ' } '
      . '} '
      . '}';
  }

}
