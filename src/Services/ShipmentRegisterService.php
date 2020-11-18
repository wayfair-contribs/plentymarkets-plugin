<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Order\Shipping\Information\Contracts\ShippingInformationRepositoryContract;
use Plenty\Modules\Order\Shipping\Information\Models\ShippingInformation;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Models\OrderShippingPackage;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\FetchDocumentContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\RegisterPurchaseOrderContract;
use Wayfair\Core\Dto\RegisterPurchaseOrder\RequestDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\ShippingLabelHelper;
use Wayfair\Core\Helpers\TimeHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;

/**
 * Class ShipmentPrintService
 *
 * @package Wayfair\Services
 */
class ShipmentRegisterService
{
  const LOG_KEY_UNABLE_TO_REGISTER_ORDER = 'unableToRegisterOrder';
  const LOG_KEY_ALREADY_REGISTERED = 'shippingRegisteredAlready';
  const LOG_KEY_LABELS_SIZE_DOES_NOT_MATCH_PACKAGES_SIZE = 'numberOfLabelsDoesNotMatchNumberOfPackages';
  const LOG_KEY_UNABLE_TO_GET_TRACKING_NUMBER = 'unableToGetTrackingNumber';
  const LOG_KEY_FAILED_TO_LOAD_PACKAGES = 'failedToLoadPackagesForOrder';
  const LOG_KEY_SAVED_SHIPMENT = 'savedShipment';
  const LOG_KEY_SHIPPING_ERROR_REGISTERED_SHIPMENT = 'shippingErrorRegisteredShipment';
  const LOG_KEY_EMPTY_ORDER_IDS_UNREGISTER = 'emptyOrderIdsUnregister';
  const LOG_KEY_SHIPPING_CANNOT_FIND_PO_NUMBER = 'shippingCannotFindPoNumber';
  const LOG_KEY_EMPTY_ORDER_IDS_PROCESS = 'emptyOrderIdsProcess';
  const LOG_KEY_SHIPPING_LABEL_RETRIEVAL_FAILED = 'shippingLabelRetrievalFailed';
  const LOG_KEY_NO_SHIPPING_INFO_FOR_UNREGISTER = 'noShippingInformationForUnregister';
  const LOG_KEY_WAREHOUSE_MISSING_FOR_ORDER = 'warehouseMissingForOrder';
  const LOG_KEY_CUSTOMS_INVOICE_NOT_REQUIRED = 'customsInvoiceNotRequired';
  const LOG_KEY_CUSTOMS_INVOICE_MISSING_URL = 'noURLForRequiredCustomsInvoice';
  const LOG_KEY_CUSTOMS_INVOICE_SAVED = 'customsInvoiceSaved';
  const LOG_KEY_CUSTOMS_INVOICE_SAVE_FAILED = 'customsInvoiceSaveFailed';

  const SHIPPING_REGISTERED_STATUS = 'registered';
  const SHIPPING_WAYFAIR_COST = 0.00;
  const GENERATED_SHIPPING_LABELS = 'generatedShippingLabels';
  const TRACKING_NUMBER = 'trackingNumber';


  /**
   * @var SaveOrderDocumentService
   */
  private $saveOrderDocumentService;
  /**
   * @var RegisterPurchaseOrderContract
   */
  private $registerPurchaseOrderContract;
  /**
   * @var OrderShippingPackageRepositoryContract
   */
  private $orderShippingPackageRepositoryContract;
  /**
   * @var ShippingInformationRepositoryContract
   */
  private $shippingInformationRepositoryContract;
  /**
   * @var StorageRepositoryContract
   */
  private $storageRepositoryContract;

  /**
   * @var FetchDocumentContract
   */
  private $fetchShippingLabelContract;

  /**
   * @var OrderPropertyService
   */
  private $orderPropertyService;

  /**
   * @var SaveCustomsInvoiceService
   */
  private $saveCustomsInvoiceService;

  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * ShippingController constructor.
   *
   * @param SaveOrderDocumentService $saveOrderDocumentService
   * @param RegisterPurchaseOrderContract $registerPurchaseOrderContract
   * @param OrderShippingPackageRepositoryContract $orderShippingPackageRepositoryContract
   * @param ShippingInformationRepositoryContract $shippingInformationRepositoryContract
   * @param StorageRepositoryContract $storageRepositoryContract
   * @param FetchDocumentContract $fetchShippingLabelContract
   * @param OrderPropertyService $orderPropertyService
   * @param SaveCustomsInvoiceService $saveCustomsInvoiceService
   * @param LoggerContract $loggerContract
   */
  public function __construct(
    SaveOrderDocumentService $saveOrderDocumentService,
    RegisterPurchaseOrderContract $registerPurchaseOrderContract,
    OrderShippingPackageRepositoryContract $orderShippingPackageRepositoryContract,
    ShippingInformationRepositoryContract $shippingInformationRepositoryContract,
    StorageRepositoryContract $storageRepositoryContract,
    FetchDocumentContract $fetchShippingLabelContract,
    OrderPropertyService $orderPropertyService,
    SaveCustomsInvoiceService $saveCustomsInvoiceService,
    LoggerContract $loggerContract
  ) {
    $this->saveOrderDocumentService = $saveOrderDocumentService;
    $this->registerPurchaseOrderContract = $registerPurchaseOrderContract;
    $this->orderShippingPackageRepositoryContract = $orderShippingPackageRepositoryContract;
    $this->shippingInformationRepositoryContract = $shippingInformationRepositoryContract;
    $this->storageRepositoryContract = $storageRepositoryContract;
    $this->fetchShippingLabelContract = $fetchShippingLabelContract;
    $this->orderPropertyService = $orderPropertyService;
    $this->saveCustomsInvoiceService = $saveCustomsInvoiceService;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Get already generated labels for list of order ids.
   *
   * @param array $orderIds
   *
   * @return array
   */
  public function getGeneratedLabels(array $orderIds): array
  {
    $labels = [];

    foreach ($orderIds as $orderId) {
      $orderShippingPackages = $this->orderShippingPackageRepositoryContract->listOrderShippingPackages($orderId);

      /* @var OrderShippingPackage $orderShippingPackage */
      foreach ($orderShippingPackages as $orderShippingPackage) {
        $labelKey = explode('/', $orderShippingPackage->labelPath)[1]; //(index 0 is for plugin name)
        if ($this->storageRepositoryContract->doesObjectExist(AbstractConfigHelper::PLUGIN_NAME, $labelKey)) {
          $storageObject = $this->storageRepositoryContract->getObject(AbstractConfigHelper::PLUGIN_NAME, $labelKey);
          $labels[] = $storageObject->body;
        }
      }
    }

    return $labels;
  }

  /**
   * Check if a shipment is already registered by this plugin
   * @param ShippingInformation $shippingInformation
   * @param int $orderId
   * @param string $poNumber
   * @param ExternalLogs $externalLogs
   * @return bool
   */
  private function shipmentIsRegistered(
    ShippingInformation $shippingInformation
  ): bool {
    return $shippingInformation !== null
      && $shippingInformation->shippingServiceProvider === AbstractConfigHelper::PLUGIN_NAME
      && $shippingInformation->shippingStatus === self::SHIPPING_REGISTERED_STATUS;
  }

  private function getTrackingNumberForPackage(
    $trackingNumbers,
    $packageIndex,
    string $poNumber,
    ExternalLogs $externalLogs
  ): string {
    // FIXME: tracking numbers may not match packages - using arbitrary indexes?
    // v 1.1.1 uses array index from plentymarkets to lookup tracking number in array from Wayfair.
    // when there is more than one package in the PO,
    // we probably need to use some metadata from package to match with tracking number.

    if (!empty($trackingNumbers) && is_array($trackingNumbers)) {
      // already logged about empty tracking numbers array above, outside of the loop.
      if ((!array_key_exists($packageIndex, $trackingNumbers)) || empty($trackingNumbers[$packageIndex])) {
        $externalLogs->addErrorLog('No tracking information for package at index ' .
          $packageIndex . ' PO:' . $poNumber);
      } else {

        $label_generation_event = $trackingNumbers[$packageIndex];

        // objects in $trackingNumbers are Label_Generation_Event_Type, not plain tracking numbers.
        // TODO: if/when FetchDocumentService is fixed/replaced, update this "get tracking number" logic
        if ((!array_key_exists(self::GENERATED_SHIPPING_LABELS, $label_generation_event)) ||
          empty($label_generation_event[self::GENERATED_SHIPPING_LABELS])
        ) {

          $externalLogs->addErrorLog('No label information in tracking info for package at index '
            . $packageIndex . ' PO:' . $poNumber);
        } else {
          $generated_label = $label_generation_event[0];

          if ((!array_key_exists(self::TRACKING_NUMBER, $generated_label)) ||
            empty($generated_label[self::TRACKING_NUMBER])
          ) {
            $externalLogs->addErrorLog('No tracking number in label info for package at index '
              . $packageIndex . '. Using PO number as tracking number! PO:' . $poNumber);
          } else {
            return $generated_label[self::TRACKING_NUMBER];
          }
        }
      }
    }

    // TODO: stop defaulting to using poNumber as tracking number?
    // this is behavior from v1.1.1
    return $poNumber;
  }

  /**
   * Process shipping labels for orders.
   *
   * @param array $orderIds
   *
   * @return array
   * @throws \Exception
   */
  public function register(array $orderIds): array
  {
    $registerResult = [];
    if (empty($orderIds)) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_EMPTY_ORDER_IDS_PROCESS),
          [
            'additionalInfo' => ['orderIds' => $orderIds],
            'method' => __METHOD__
          ]
        );

      return $registerResult;
    }

    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    $purchaseOrdersToRegister = 0;
    $purchaseOrdersRegisterFailed = 0;
    $purchaseOrdersRegisterDuration = 0;
    $shippingLabelsToGenerate = 0;
    $shippingLabelsGenerateFailed = 0;
    $shippingLabelsGenerateDuration = 0;

    try {
      foreach ($orderIds as $orderId) {
        $poNumber = $this->orderPropertyService->getCheckedPoNumber($orderId);

        if (!isset($poNumber) || empty($poNumber)) {

          $errorMessage = sprintf(TranslationHelper::translate(self::LOG_KEY_SHIPPING_CANNOT_FIND_PO_NUMBER), $poNumber);

          $registerResult[$orderId] = $this->buildResultMessage(false, $errorMessage, []);

          $this->loggerContract
            ->debug(
              TranslationHelper::getLoggerKey(self::LOG_KEY_SHIPPING_CANNOT_FIND_PO_NUMBER),
              [
                'additionalInfo' => ['orderId' => $orderId],
                'method' => __METHOD__
              ]
            );

          $externalLogs->addErrorLog('Cannot register shipment! Cannot find the PO number. Plentymarkets Order:' . $orderId);

          continue;
        }

        try {
          $shippingInformation = $this->getOrderShippingInformation($orderId);

          if ($this->shipmentIsRegistered($shippingInformation)) {

            // If order has already been registered with Wayfair, ignore and alert supplier.
            $registerResult[$orderId] =
              $this->buildResultMessage(
                false,
                sprintf(
                  TranslationHelper::translate(self::LOG_KEY_ALREADY_REGISTERED),
                  $shippingInformation->shippingServiceProvider,
                  $orderId
                ),
                []
              );

            $this->loggerContract->info(
              TranslationHelper::getLoggerKey(self::LOG_KEY_ALREADY_REGISTERED),
              [
                'additionalInfo' => [
                  'orderId' => $orderId,
                  'po' => $poNumber,
                ],
                'method' => __METHOD__
              ]
            );

            $externalLogs->addInfoLog('Already registered PO: ' . $poNumber .
              ' . Order:' . $orderId);

            continue;
          }

          $externalLogs->addDebugLog('Obtaining packages for order ' . $orderId);

          $msLoadStart = TimeHelper::getMilliseconds();
          try {
            $packages = $this->orderShippingPackageRepositoryContract->listOrderShippingPackages($orderId);
          } catch (\Exception $e) {
            $purchaseOrdersRegisterFailed++;
            $externalLogs->addShippingLabelLog('Failed to load packages for ', 'orderLoadFailed', 0, TimeHelper::getMilliseconds() - $msLoadStart);
            $externalLogs->addErrorLog('Order load failed' . ' Order:' . $orderId . 'PO:' . $poNumber . ' - ' . get_class($e) . ': ' . $e->getMessage());

            $this->loggerContract
              ->error(
                TranslationHelper::getLoggerKey(self::LOG_KEY_FAILED_TO_LOAD_PACKAGES),
                [
                  'additionalInfo' => [
                    'orderId' => $orderId,
                    'po' => $poNumber,
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'stacktrace' => $e->getTrace()

                  ],
                  'method' => __METHOD__,
                  'referenceType' => 'orderId',
                  'referenceValue' => $orderId
                ]
              );

            continue;
          }

          $amtPackages = count($packages);
          $externalLogs->addDebugLog('There are ' . $amtPackages . '. PO: ' . $poNumber);

          /**
           * @var RequestDTO $requestDto
           */
          $requestDto = pluginApp(RequestDTO::class);
          $requestDto->setPoNumber($poNumber);
          $warehouseId = $this->orderPropertyService->getWarehouseId($orderId);

          if (!isset($warehouseId) || empty($warehouseId)) {
            $externalLogs->addErrorLog('Warehouse ID missing' . ' Order:' . $orderId . 'PO:' . $poNumber);

            $this->loggerContract
              ->error(
                TranslationHelper::getLoggerKey(self::LOG_KEY_WAREHOUSE_MISSING_FOR_ORDER),
                [
                  'additionalInfo' => [
                    'orderId' => $orderId,
                    'po' => $poNumber,

                  ],
                  'method' => __METHOD__,
                  'referenceType' => 'orderId',
                  'referenceValue' => $orderId
                ]
              );

            continue;
          }

          $requestDto->setWarehouseId($warehouseId);

          $msRegistrationStart = TimeHelper::getMilliseconds();

          try {
            $purchaseOrdersToRegister++;
            $registerResponse = $this->registerPurchaseOrderContract->register($requestDto);
            $purchaseOrdersRegisterDuration = $purchaseOrdersRegisterDuration + (TimeHelper::getMilliseconds() - $msRegistrationStart);
          } catch (\Exception $e) {
            $purchaseOrdersRegisterFailed++;
            $externalLogs->addShippingLabelLog('Purchase order register failed', 'purchaseOrderRegisterFailed', 0, TimeHelper::getMilliseconds() - $msRegistrationStart);
            $externalLogs->addErrorLog('Registration failed' . 'Order:' . $orderId . 'PO:' . $poNumber . ' - ' . get_class($e) . ': ' . $e->getMessage());

            // this duplicates some information logged in RegisterPurchaseOrderService,
            // but we are writing against the Interface here and should log everything we want to know about.
            $this->loggerContract
              ->error(
                TranslationHelper::getLoggerKey(self::LOG_KEY_UNABLE_TO_REGISTER_ORDER),
                [
                  'additionalInfo' => [
                    'orderId' => $orderId,
                    'po' => $poNumber,
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'stacktrace' => $e->getTrace()

                  ],
                  'method' => __METHOD__,
                  'referenceType' => 'orderId',
                  'referenceValue' => $orderId
                ]
              );
            continue;
          }

          $trackingNumbers = [];
          try {
            // tracking number argument for fetchShippingLabelContract must be WITHOUT prefix.
            $poNumberWithoutPrefix = $registerResponse->getPoNumber();
            if ($poNumberWithoutPrefix < 1) {
              throw new \Exception("Registration response is missing the PO number");
            }
            $trackingNumbers = $this->fetchShippingLabelContract->getTrackingNumber($poNumberWithoutPrefix);
          } catch (\Exception $exception) {
            $externalLogs->addErrorLog('Unable to get the tracking number, PO:' . $poNumber . ' - ' . get_class($exception) . ': ' . $exception->getMessage());
            $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_UNABLE_TO_GET_TRACKING_NUMBER), [
              'additionalInfo' => [
                'orderId' => $orderId,
                'po' => $poNumber,
                'exception' => $exception,
                'message' => $exception->getMessage(),
                'stacktrace' => $exception->getTrace()
              ],
              'method' => __METHOD__
            ]);
          }

          $amtTrackingNumbers = 0;
          if (empty($trackingNumbers) || !is_array($trackingNumbers)) {
            $externalLogs->addErrorLog('Unable to get tracking numbers. PO:' . $poNumber);

            $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_UNABLE_TO_GET_TRACKING_NUMBER), [
              'additionalInfo' => [
                'orderId' => $orderId,
                'po' => $poNumber,
              ],
              'method' => __METHOD__
            ]);
          } else {
            $amtTrackingNumbers = count($trackingNumbers);
          }

          if ($amtTrackingNumbers != $amtPackages) {
            $externalLogs->addDebugLog('Amount of tracking numbers(' . $amtTrackingNumbers .
              ') does not match amount of packages (' . $amtPackages . ') PO:' . $poNumber);

            $this->loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_LABELS_SIZE_DOES_NOT_MATCH_PACKAGES_SIZE), [
              'additionalInfo' => [
                'orderId' => $orderId,
                'po' => $poNumber,
                'amtTrackingNumbers' => $amtTrackingNumbers,
                'amtPackages' => $amtPackages,
                'trackingNumbers' => $trackingNumbers,
                'packages' => $packages
              ],
              'method' => __METHOD__
            ]);
          }

          /**
           * @var OrderShippingPackage $package
           */
          foreach ($packages as $index => $package) {
            $packageId = $package->id;
            $packageFileName = ShippingLabelHelper::generateLabelFileName($poNumber, $packageId);

            $msSaveStart = TimeHelper::getMilliseconds();
            try {
              $shippingLabelsToGenerate++;
              $storageObject = $this->saveOrderDocumentService->savePoShippingLabel($registerResponse, $packageFileName);
              $shippingLabelsGenerateDuration = $shippingLabelsGenerateDuration +
                (TimeHelper::getMilliseconds() - $msSaveStart);
            } catch (\Exception $e) {
              $shippingLabelsGenerateFailed++;
              $externalLogs->addShippingLabelLog(
                'Shipping label retrieve failed',
                'shippingLabelRetrieveFailed',
                $shippingLabelsGenerateFailed,
                TimeHelper::getMilliseconds() - $msSaveStart
              );
              $externalLogs->addErrorLog('Shipping label retrieval failed. PO:' . $poNumber .
                " " . get_class($e) . ": " . $e->getMessage());

              $this->loggerContract
                ->error(
                  TranslationHelper::getLoggerKey(self::LOG_KEY_SHIPPING_LABEL_RETRIEVAL_FAILED),
                  [
                    'additionalInfo' => [
                      'orderId' => $orderId,
                      'po' => $poNumber,
                      'exception' => $e,
                      'message' => $e->getMessage(),
                      'stacktrace' => $e->getTrace()

                    ],
                    'method' => __METHOD__,
                    'referenceType' => 'packageId',
                    'referenceValue' => $packageId
                  ]
                );

              continue;
            }

            $trackingNumber = $this->getTrackingNumberForPackage($trackingNumbers, $index, $poNumber, $externalLogs);
            if (!isset($trackingNumber) || empty($trackingNumber)) {

              $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_KEY_UNABLE_TO_GET_TRACKING_NUMBER), [
                'additionalInfo' => [
                  'packageIndex' => $index,
                  'orderId' => $orderId,
                  'po' => $poNumber,
                ],
                'method' => __METHOD__
              ]);

              $externalLogs->addErrorLog('Failed to get tracking number. PO:' . $poNumber . " Package ID: " . $packageId);
            }

            // TODO: internal info log for this tracking number
            $externalLogs->addInfoLog('Using tracking number ' . $trackingNumber . ' for package ' .
              $index . ' from PO ' . $poNumber);

            $this->orderShippingPackageRepositoryContract->updateOrderShippingPackage(
              $packageId,
              [
                'packageNumber' => $trackingNumber,
                'label' => $storageObject->key,
                'labelPath' => $storageObject->key
              ]
            );

            $objectUrl = $this->storageRepositoryContract->getObjectUrl(
              AbstractConfigHelper::PLUGIN_NAME,
              $packageFileName
            );

            $shipmentNumber = ShippingLabelHelper::generateShipmentNumber($poNumber, $packageId);

            $shipmentItems = [
              'labelUrl' => $objectUrl,
              'shipmentNumber' => $shipmentNumber
            ];

            $this->saveShippingInformation($orderId, $shipmentItems);

            $this->loggerContract
              ->info(
                TranslationHelper::getLoggerKey(self::LOG_KEY_SAVED_SHIPMENT),
                [
                  'additionalInfo' => [
                    'orderId' => $orderId,
                    'shipmentItems' => $shipmentItems,
                    'poNumber' => $poNumber,
                    'trackingNumber' => $trackingNumber,
                    'shipmentNumber' => $shipmentNumber
                  ],
                  'method' => __METHOD__,
                ]
              );

            $externalLogs->addInfoLog("Saved information for shipment " . $shipmentNumber .
              " for order with ID " . $orderId);

            $registerResult[$orderId] = $this->buildResultMessage(
              true,
              TranslationHelper::translate('shippingRegisterMessage') . $orderId,
              false,
              $shipmentItems
            );
          }

          $customsDocument = $registerResponse->getCustomsDocument();

          if (isset($customsDocument) && $customsDocument->getRequired()) {
            $url = $customsDocument->getUrl();
            if (isset($url) && !empty(trim($url))) {
              try {
                $customsDocumentSaveResult = $this->saveCustomsInvoiceService->save($orderId, $poNumber, $url);
                $this->loggerContract
                  ->info(
                    TranslationHelper::getLoggerKey(self::LOG_KEY_CUSTOMS_INVOICE_SAVED),
                    [
                      'additionalInfo' => [
                        'orderId' => $orderId,
                        'poNumber' => $poNumber,
                        'customsDocumentSaveResult' => $customsDocumentSaveResult
                      ],
                      'method' => __METHOD__,
                    ]
                  );
              } catch (\Exception $exception) {
                $this->loggerContract
                  ->error(
                    TranslationHelper::getLoggerKey(self::LOG_KEY_CUSTOMS_INVOICE_SAVE_FAILED),
                    [
                      'additionalInfo' => [
                        'exceptionType' => get_class($exception),
                        'exceptionMessage' => $exception->getMessage(),
                        'orderId' => $orderId,
                        'poNumber' => $poNumber,
                      ],
                      'method' => __METHOD__,
                    ]
                  );

                $externalLogs->addErrorLog('Customs Invoice save failed for Order: ' .$orderId . ' PO:' . $poNumber . ' - '
                  . get_class($exception) . ': '
                  . $exception->getMessage(), $exception->getTraceAsString());
              }
            } else {
              $this->loggerContract
                ->error(
                  TranslationHelper::getLoggerKey(self::LOG_KEY_CUSTOMS_INVOICE_MISSING_URL),
                  [
                    'additionalInfo' => [
                      'orderId' => $orderId,
                      'poNumber' => $poNumber,
                    ],
                    'method' => __METHOD__,
                  ]
                );

                $externalLogs->addErrorLog('Customs Invoice URL is missing but required flag is true, Order: ' .$orderId . ' PO:' . $poNumber);
            }
          } else {
            $this->loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CUSTOMS_INVOICE_NOT_REQUIRED), [
              'additionalInfo' => [
                'orderId' => $orderId,
                'po' => $poNumber,
              ],
              'method' => __METHOD__
            ]);
          }
        } catch (\Exception $exception) {
          $externalLogs->addErrorLog('Registration process failed, PO:' . $poNumber . ' - '
            . get_class($exception) . ': ' . $exception->getMessage());
          $errorMessage = sprintf(TranslationHelper::translate(self::LOG_KEY_SHIPPING_ERROR_REGISTERED_SHIPMENT), $orderId);
          $registerResult[$orderId] = $this->buildResultMessage(false, $errorMessage, []);

          $this->loggerContract
            ->error(
              TranslationHelper::getLoggerKey(self::LOG_KEY_SHIPPING_ERROR_REGISTERED_SHIPMENT),
              [
                'additionalInfo' => [
                  'orderId' => $orderId,
                  'po' => $poNumber,
                  'exception' => $exception,
                  'message' => $exception->getMessage(),
                  'stacktrace' => $exception->getTrace()
                ],
                'method' => __METHOD__,
                'referenceType' => 'orderId',
                'referenceValue' => $orderId
              ]
            );
        }
      }

      return $registerResult;
    } finally {
      if ($purchaseOrdersToRegister > 0) {
        $externalLogs->addShippingLabelLog(
          'Purchase order register',
          'purchaseOrderRegister',
          $purchaseOrdersToRegister,
          $purchaseOrdersRegisterDuration
        );
        $externalLogs->addShippingLabelLog(
          'Purchase order registered',
          'purchaseOrderRegistered',
          $purchaseOrdersToRegister - $purchaseOrdersRegisterFailed,
          $purchaseOrdersRegisterDuration
        );
      }
      if ($shippingLabelsToGenerate > 0) {
        $externalLogs->addShippingLabelLog(
          'Shipping label retrieve',
          'shippingLabelRetrieve',
          $shippingLabelsToGenerate,
          $shippingLabelsGenerateDuration
        );
        $externalLogs->addShippingLabelLog(
          'Shipping label retrieved',
          'shippingLabelRetrieved',
          $shippingLabelsToGenerate - $shippingLabelsGenerateFailed,
          $shippingLabelsGenerateDuration
        );
      }

      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }

  /**
   * Remove orders from wayfair shipment.
   *
   * @param array $orderIds
   *
   * @return array
   */
  public function unregister(array $orderIds): array
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    try {
      $registerResult = [];
      if (empty($orderIds)) {

        $this->loggerContract
          ->warning(
            TranslationHelper::getLoggerKey(self::LOG_KEY_EMPTY_ORDER_IDS_UNREGISTER),
            [
              'additionalInfo' => ['orderIds' => $orderIds],
              'method' => __METHOD__
            ]
          );

        return $registerResult;
      }

      foreach ($orderIds as $orderId) {

        $shippingInformation = $this->getOrderShippingInformation($orderId);
        //Check if shipping information is correct

        if (isset($shippingInformation)) {
          $registerResult[$orderId] = $this->buildResultMessage(
            true,
            sprintf(TranslationHelper::translate('shippingUnregisterSuccessfully'), $orderId),
            []
          );
          $this->shippingInformationRepositoryContract->resetShippingInformation($orderId);
        } else {
          $this->loggerContract
            ->error(
              TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SHIPPING_INFO_FOR_UNREGISTER),
              [
                'additionalInfo' => [
                  'orderId' => $orderId,
                ],
                'method' => __METHOD__,
                'referenceType' => 'orderId',
                'referenceValue' => $orderId
              ]
            );

          $externalLogs->addErrorLog("Cannot unregister shipments - no shipping information for Order " . $orderId);
        }
      }

      return $registerResult;
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }

  /**
   * Returns an array in the structure demanded by plenty service
   *
   * @param bool $success
   * @param string $statusMessage
   * @param bool $newShippingPackage
   * @param array $shipmentItems
   *
   * @return array
   */
  private function buildResultMessage(
    $success = false,
    $statusMessage = '',
    $newShippingPackage = false,
    $shipmentItems = []
  ): array {
    return [
      'success' => $success,
      'message' => $statusMessage,
      'newPackagenumber' => $newShippingPackage,
      'packages' => $shipmentItems,
    ];
  }

  /**
   * Update shipping information back to PM
   *
   * @param int $orderId
   * @param array $shipmentItems
   *
   * @return ShippingInformation
   */
  private function saveShippingInformation(int $orderId, array $shipmentItems): ShippingInformation
  {
    $transactionIds = [];
    foreach ($shipmentItems as $shipmentItem) {
      $transactionIds[] = $shipmentItem['shipmentNumber'];
    }

    $shipmentDate = date('Y-m-d');
    $shipmentAt = date(\DateTime::W3C, strtotime($shipmentDate));
    $registrationAt = date(\DateTime::W3C);

    $data = [
      'orderId' => $orderId,
      'transactionId' => implode(',', $transactionIds),
      'shippingServiceProvider' => AbstractConfigHelper::PLUGIN_NAME,
      'shippingStatus' => self::SHIPPING_REGISTERED_STATUS,
      'shippingCosts' => self::SHIPPING_WAYFAIR_COST,
      'additionalData' => $shipmentItems,
      'registrationAt' => $registrationAt,
      'shipmentAt' => $shipmentAt

    ];

    return $this->shippingInformationRepositoryContract->saveShippingInformation($data);
  }

  /**
   * Get a shipping information for an order.
   *
   * @param mixed $orderId
   *
   * @return ShippingInformation
   */
  private function getOrderShippingInformation($orderId): ShippingInformation
  {
    $shippingInformation = $this->shippingInformationRepositoryContract->getShippingInformationByOrderId($orderId);

    return $shippingInformation;
  }
}
