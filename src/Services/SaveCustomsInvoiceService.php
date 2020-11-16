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
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;

/**
 * Service class to fetch and save Customs Invoice document for order.
 *
 * @package Wayfair\Services
 */
class SaveCustomsInvoiceService
{
  const LOG_KEY_DOWNLOAD_ERROR = 'customsInvoiceDownloadFailure';
  const LOG_KEY_UPLOADING_ORDER_DOCUMENTS = 'uploadOrderDocuments';
  const LOG_KEY_FINISHED_UPLOADING_ORDER_DOCUMENTS = 'finishedUploadOrderDocuments';
  const LOG_KEY_STARTED_FETCHING = 'fetchingCustomsInvoice';
  const LOG_KEY_FINISHED_FETCHING = 'finishedFetchingCustomsInvoice';
  const LOG_KEY_SAVE_ERROR = 'saveCustomsInvoiceError';
  const LOG_KEY_NO_CUSTOMS_INVOICE_DATA = 'noCustomsInvoiceData';

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
   * @var LogSenderService
   */
  private $logSenderService;

  /**
   * SaveCustomsInvoiceService constructor.
   *
   * @param DocumentRepositoryContract $documentRepositoryContract
   * @param FetchDocumentContract $fetchDocumentContract
   * @param LoggerContract $loggerContract
   */
  public function __construct(
    DocumentRepositoryContract $documentRepositoryContract,
    FetchDocumentContract $fetchDocumentContract,
    LoggerContract $loggerContract,
    LogSenderService $logSenderService
  )
  {
    $this->documentRepositoryContract = $documentRepositoryContract;
    $this->fetchDocumentContract = $fetchDocumentContract;
    $this->loggerContract = $loggerContract;
    $this->logSenderService = $logSenderService;
  }

  /**
   * Build the plentymarkets document data array
   * @param string $poNumberWithPrefix
   * @param string $contentBase64
   * @return array
   */
  private function buildDocumentData(string $contentBase64): array
  {
    return [
      'documents' => [
        [
          'content' => $contentBase64,
        ]
      ]
    ];
  }

  /**
   * Fetch a document from the Wayfair API URL in the parameter
   * Add the file as an "external invoice" document to the order with the ID in the parameter
   *
   * @param int $plentyOrderId the ID of the Plenty Order where the document should be placed
   * @param string $wfPoNumber the ID of the Wayfair Purchase Order (for logging)
   * @param string $documentURL the URL for fetching the document
   *
   * @return array
   */
  public function save(int $plentyOrderId, string $wfPoNumber, string $documentURL): array
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    $this->loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_STARTED_FETCHING),
      [
        'additionalInfo' => [
          'plentyOrderId' => $plentyOrderId,
          'wfPoNumber' => $wfPoNumber,
          'documentURL' => $documentURL
        ],
        'method' => __METHOD__
      ]
    );

    try {

      $docDto = $this->fetchDocumentContract->fetch($documentURL);

      $contentBase64 = $docDto->getBase64EncodedContent();
    } catch (\Exception $exception) {
      $this->loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_DOWNLOAD_ERROR),
        [
          'additionalInfo' => [
            'exception' => $exception,
            'message' => $exception->getMessage(),
            'stackTrace' => $exception->getTrace(),
            'plentyOrderId' => $plentyOrderId,
            'wfPoNumber' => $wfPoNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addErrorLog("PO " . $wfPoNumber . " for order " . $plentyOrderId . ": unable to fetch customs invoice - " .
        get_class($exception) . ": " . $exception->getMessage());

      return [];
    }

    if (!isset($contentBase64) && empty($contentBase64)) {
      $this->loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_NO_CUSTOMS_INVOICE_DATA),
        [
          'additionalInfo' => [
            'plentyOrderId' => $plentyOrderId,
            'wfPoNumber' => $wfPoNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addErrorLog("Unable to fetch Customs Invoice. PO: " . $wfPoNumber . " ORDER: " . $plentyOrderId);

      return [];
    }

    $this->loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_FINISHED_FETCHING),
      [
        'additionalInfo' => [
          'plentyOrderId' => $plentyOrderId,
          'wfPoNumber' => $wfPoNumber,
        ],
        'method' => __METHOD__
      ]
    );

    try {
      $documentData = $this->buildDocumentData($contentBase64);
      $docType = Document::PRO_FORMA_INVOICE;

      $this->loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_UPLOADING_ORDER_DOCUMENTS),
        [
          'additionalInfo' => [
            'plentyOrderId' => $plentyOrderId,
            'wfPoNumber' => $wfPoNumber,
            'docType' => $docType
          ],
          'method' => __METHOD__
        ]
      );

      $upload_result = $this->documentRepositoryContract->uploadOrderDocuments($plentyOrderId, $docType, $documentData);

      $this->loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_FINISHED_UPLOADING_ORDER_DOCUMENTS),
        [
          'additionalInfo' => [
            'plentyOrderId' => $plentyOrderId,
            'wfPoNumber' => $wfPoNumber,
            'docType' => $docType
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addDebugLog("Finished uploading Customs Invoice to Plentymarkets. Result: "
        . json_encode($upload_result));

      return $upload_result;

    } catch (\Exception $exception) {
      $this->loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_SAVE_ERROR),
        [
          'additionalInfo' => [
            'exception' => $exception,
            'message' => $exception->getMessage(),
            'stackTrace' => $exception->getTraceAsString(),
            'plentyOrderId' => $plentyOrderId,
            'wfPoNumber' => $wfPoNumber,
          ],
          'method' => __METHOD__
        ]
      );

      $externalLogs->addErrorLog("PO " . $wfPoNumber . " for order " . $plentyOrderId . ": unable to save Customs Invoice - " .
        get_class($exception) . ": " . $exception->getMessage(), $exception->getTraceAsString());

      return [];

    } finally {
      if (isset($this->logSenderService) && count($externalLogs->getLogs())) {
        $this->logSenderService->execute($externalLogs->getLogs());
      }
    }
  }
}
