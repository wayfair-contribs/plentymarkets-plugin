<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Document\Contracts\DocumentRepositoryContract;
use Plenty\Modules\Document\Models\Document;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\FetchDocumentContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Helpers\ShippingLabelHelper;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;

/**
 * Service class to fetch and save packing slip document for order.
 *
 * @package Wayfair\Services
 */
class SavePackingSlipService
{
  const LOG_KEY_DOWNLOAD_ERROR = 'downloadPackingSlipToOrderError';
  const LOG_KEY_UPLOADING_ORDER_DOCUMENTS = 'uploadOrderDocuments';
  const LOG_KEY_FINISHED_UPLOADING_ORDER_DOCUMENTS = 'finishedUploadOrderDocuments';
  const LOG_KEY_NO_PACKING_SLIP = 'packingSlipNotFound';
  const LOG_KEY_FETCHING_PACKING_SLIP = 'fetchingPackingSlip';
  const LOG_KEY_FINISHED_FETCHING_PACKING_SLIP = 'finishedFetchingPackingSlip';
  const LOG_KEY_SAVE_ERROR = 'savePackingSlipError';
  /**
   * @var DocumentRepositoryContract
   */
  private $documentRepositoryContract;

  /**
   * @var FetchDocumentContract
   */
  private $fetchDocumentContract;

  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * SavePackingSlipService constructor.
   *
   * @param DocumentRepositoryContract $documentRepositoryContract
   * @param FetchDocumentContract $fetchDocumentContract
   * @param LoggerContract $loggerContract
   */
  public function __construct(
    DocumentRepositoryContract $documentRepositoryContract,
    FetchDocumentContract $fetchDocumentContract,
    LoggerContract $loggerContract
  ) {
    $this->documentRepositoryContract = $documentRepositoryContract;
    $this->fetchDocumentContract = $fetchDocumentContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Fetch a packing slip from WF api, as base64-encoded data
   * @param string $poNumber
   * @return string
   * @throws \Exception
   */
  private function fetchPackingSlip(string $poNumber): string
  {

    $packingSlipUrl = URLHelper::getPackingSlipUrl($poNumber);

    try {
      $packingSlipDTO = $this->fetchDocumentContract->fetch($packingSlipUrl);
    } catch (\Exception $e) {
      throw new \Exception("Packing Slip fetch failed : " . $e);
    }

    if (isset($packingSlipDTO) && !empty($packingSlipDTO->getFileContent())) {
      return $packingSlipDTO->getBase64EncodedContent();
    }

    return '';
  }

  /**
   * Build the plentymarkets document data array
   * @param string $poNumberWithPrefix
   * @param string $contentBase64
   * @return array
   */
  private function buildDocumentData(string $poNumberWithPrefix, string $contentBase64): array
  {
    return [
      'documents' => [
        [
          'content' => $contentBase64,
          'numberWithPrefix' => $poNumberWithPrefix,
          'number' => ShippingLabelHelper::removePoNumberPrefix($poNumberWithPrefix)
        ]
      ]
    ];
  }

  /**
   * Fetch a packing slip from WF api,
   * add/ update the order document "delivery_note" with this packing slip file.
   *
   * @param int $orderId
   * @param string $poNumber
   *
   * @return array
   */
  public function save(int $orderId, string $poNumber): array
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    $this->loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_FETCHING_PACKING_SLIP),
      [
        'additionalInfo' => [
          'orderId' => $orderId,
          'po' => $poNumber,
        ],
        'method' => __METHOD__
      ]
    );

    try {
      $contentBase64 = $this->fetchPackingSlip($poNumber);
    } catch (\Exception $exception) {
      $this->loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_DOWNLOAD_ERROR),
        [
          'additionalInfo' => [
            'exception' => $exception,
            'message' => $exception->getMessage(),
            'stackTrace' => $exception->getTrace(),
            'orderId' => $orderId,
            'po' => $poNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addErrorLog("PO " . $poNumber . " for order " . $orderId . ": unable to fetch packing slip - " .
        get_class($exception) . ": " . $exception->getMessage());

      return [];
    }

    if (empty($contentBase64)) {
      $this->loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_NO_PACKING_SLIP),
        [
          'additionalInfo' => [
            'orderId' => $orderId,
            'po' => $poNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addErrorLog("Unable to fetch packing slip. PO: " . $poNumber . " ORDER: " . $orderId);

      return [];
    }

    $this->loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_FINISHED_FETCHING_PACKING_SLIP),
      [
        'additionalInfo' => [
          'orderId' => $orderId,
          'po' => $poNumber,
        ],
        'method' => __METHOD__
      ]
    );

    try {
      $documentData = $this->buildDocumentData($poNumber, $contentBase64);

      $this->loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_UPLOADING_ORDER_DOCUMENTS),
        [
          'additionalInfo' => [
            'orderId' => $orderId,
            'po' => $poNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $upload_result = $this->documentRepositoryContract->uploadOrderDocuments($orderId, Document::DELIVERY_NOTE, $documentData);

      $this->loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_FINISHED_UPLOADING_ORDER_DOCUMENTS),
        [
          'additionalInfo' => [
            'orderId' => $orderId,
            'po' => $poNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addDebugLog("Finished uploading Packing Slip to Plentymarkets. Result: "
        . json_encode($upload_result));

      return $upload_result;
    } catch (\Exception $exception) {
      $this->loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_SAVE_ERROR),
        [
          'additionalInfo' => [
            'exception' => $exception,
            'message' => $exception->getMessage(),
            'stackTrace' => $exception->getTrace(),
            'orderId' => $orderId,
            'po' => $poNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addErrorLog("PO " . $poNumber . " for order " . $orderId . ": unable to save packing slip - " .
        get_class($exception) . ": " . $exception->getMessage());

      return [];
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }
}
