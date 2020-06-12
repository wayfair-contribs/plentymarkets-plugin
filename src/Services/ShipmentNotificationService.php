<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Order\Shipping\Information\Contracts\ShippingInformationRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Order\Shipping\Package\Models\OrderShippingPackage;
use Wayfair\Core\Api\Services\ASNService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Api\Services\PurchaseOrderService;
use Wayfair\Core\Contracts\FetchDocumentContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Core\Dto\ShipNotice\RequestDTO;
use Wayfair\Core\Dto\ShipNotice\ShipNoticeAddressDTO;
use Wayfair\Core\Helpers\BillingAddress;
use Wayfair\Core\Helpers\ShippingLabelHelper;
use Wayfair\Core\Helpers\TimeHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Repositories\CarrierScacRepository;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Repositories\OrderASNRepository;

/**
 * Class ShipmentNotificationService
 *
 * @package Wayfair\Services
 */
class ShipmentNotificationService
{
  const LOG_KEY_CANNOT_SEND_ASN = 'cannotSendASN';
  const LOG_KEY_CANNOT_CREATE_ASN_REQUEST_BODY = 'cannotCreateAsnRequestBody';
  const LOG_KEY_DEBUG_ORDER_PURCHASE_ORDER_AND_SHIPPING_INFO = 'debugOrderPurchaseOrderAndShippingInfo';
  const LOG_KEY_TRACKING_NUMBER_EMPTY_BUT_NOT_REQUIRED_FOR_ASN = 'trackingNumberEmptyButNotRequiredForASN';
  const LOG_KEY_SHIPPING_ON_WAYFAIR = 'shippingOnWayfair';
  const LOG_KEY_SHIPPING_ON_OWN_ACCOUNT = 'shippingOnOwnAccount';
  const LOG_KEY_SHIPPING_CANNOT_GET_PO_DATA = 'unableToGetPurchaseOrderData';
  const LOG_KEY_WAYFAIR_MISSING_SHIPPING_INFO = 'wayfairMissingShippingInfoForPO';
  const LOG_KEY_PM_MISSING_SHIPPING_INFO = 'pmMissingShippingInfoForOrder';
  const LOG_KEY_PM_MISSING_BILLING_INFO = 'pmMissingBillingInfoForOrder';
  const LOG_KEY_PM_MISSING_DELIVERY_ADDRESS = 'pmMissingDeliveryAddressForOrder';

  const TRACKING_NUMBER = 'trackingNumber';
  const MISSING_TRACKING_NUMBER_FOR_ASN = 'ASN for Order %d - PO %s - is being sent without a tracking number';
  const GENERATED_SHIPPING_LABELS = 'generatedShippingLabels';

  /**
   * @var ASNService
   */
  private $asnService;

  /**
   * @var OrderPropertyService
   */
  private $orderPropertyService;

  /**
   * @var ShippingInformationRepositoryContract
   */
  private $shippingInformationRepositoryContract;

  /**
   * @var OrderShippingPackageRepositoryContract
   */
  private $orderShippingPackageRepositoryContract;

  /**
   * @var OrderASNRepository
   */
  private $orderASNRepository;

  /**
   * @var StorageInterfaceContract
   */
  private $storageInterfaceContract;

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @var CarrierScacRepository
   */
  private $carrierScacRepository;

  /**
   * @var ShipmentProviderService
   */
  private $shipmentProviderService;

  /**
   * @var PurchaseOrderService
   */
  private $purchaseOrderService;

  /**
   * @var FetchDocumentContract
   */
  private $fetchShippingLabelContract;

  /**
   * @var OrderRepositoryContract
   */
  private $orderRepositoryContract;

  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * ShipmentNotificationService constructor.
   *
   * @param ASNService $asnService
   * @param OrderPropertyService $orderPropertyService
   * @param ShippingInformationRepositoryContract $shippingInformationRepositoryContract
   * @param OrderShippingPackageRepositoryContract $orderShippingPackageRepositoryContract
   * @param OrderASNRepository $orderASNRepository
   * @param StorageInterfaceContract $storageInterfaceContract
   * @param KeyValueRepository $keyValueRepository
   * @param CarrierScacRepository $carrierScacRepository
   * @param ShipmentProviderService $shipmentProviderService
   * @param PurchaseOrderService $purchaseOrderService
   * @param FetchDocumentContract $fetchShippingLabelContract
   * @param OrderRepositoryContract $orderRepositoryContract
   * @param LoggerContract $loggerContract
   */
  public function __construct(
    ASNService $asnService,
    OrderPropertyService $orderPropertyService,
    ShippingInformationRepositoryContract $shippingInformationRepositoryContract,
    OrderShippingPackageRepositoryContract $orderShippingPackageRepositoryContract,
    OrderASNRepository $orderASNRepository,
    StorageInterfaceContract $storageInterfaceContract,
    KeyValueRepository $keyValueRepository,
    CarrierScacRepository $carrierScacRepository,
    ShipmentProviderService $shipmentProviderService,
    PurchaseOrderService $purchaseOrderService,
    FetchDocumentContract $fetchShippingLabelContract,
    OrderRepositoryContract $orderRepositoryContract,
    LoggerContract $loggerContract
  )
  {
    $this->asnService = $asnService;
    $this->orderPropertyService = $orderPropertyService;
    $this->shippingInformationRepositoryContract = $shippingInformationRepositoryContract;
    $this->orderShippingPackageRepositoryContract = $orderShippingPackageRepositoryContract;
    $this->orderASNRepository = $orderASNRepository;
    $this->storageInterfaceContract = $storageInterfaceContract;
    $this->keyValueRepository = $keyValueRepository;
    $this->carrierScacRepository = $carrierScacRepository;
    $this->shipmentProviderService = $shipmentProviderService;
    $this->purchaseOrderService = $purchaseOrderService;
    $this->fetchShippingLabelContract = $fetchShippingLabelContract;
    $this->orderRepositoryContract = $orderRepositoryContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Notify Wayfair of the order that has been shipped.
   *
   * @param Order $order
   *
   * @return bool
   * @throws \Exception
   */
  public function notifyShipment(Order $order): bool
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    try {
      try {
        $requestDto = $this->prepareRequestDto($order);
      } catch (\Exception $exception) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_CANNOT_CREATE_ASN_REQUEST_BODY), [
              'additionalInfo' => [
                'exception' => $exception,
                'message' => $exception->getMessage(),
                'stackTrace' => $exception->getTrace(),
                'order' => $order
              ],
              'method' => __METHOD__
            ]
          );

        $externalLogs->addErrorLog("Cannot create ASN request - "
          . get_class($exception) . ": " . $exception->getMessage());

        return false;
      }

      if (!isset($requestDto) || empty($requestDto)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_CANNOT_CREATE_ASN_REQUEST_BODY), [
              'additionalInfo' => [
                'order' => $order
              ],
              'method' => __METHOD__
            ]
          );

        $externalLogs->addErrorLog("Cannot create ASN request for order with ID " . $order->id);
        return false;
      }

      $sent = false;
      $timestampBeforeSend = TimeHelper::getMilliseconds();
      try {
        $sent = $this->asnService->sendASN($requestDto);
      } catch (\Exception $exception) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_CANNOT_SEND_ASN), [
              'additionalInfo' => [
                'exception' => $exception,
                'message' => $exception->getMessage(),
                'stackTrace' => $exception->getTrace(),
                'order' => $order
              ],
              'method' => __METHOD__
            ]
          );

        $externalLogs->addErrorLog("Cannot send ASN to Wayfair - "
          . get_class($exception) . ": " . $exception->getMessage());
      }

      if ($sent) {
        $externalLogs->addASNLog('ASN success', 'asnSuccess', 1,
          TimeHelper::getMilliseconds() - $timestampBeforeSend);
        $this->logASNSentRecord($order);
        return true;
      }

      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_CANNOT_SEND_ASN), [
            'additionalInfo' => [
              'order' => $order
            ],
            'method' => __METHOD__
          ]
        );

      $externalLogs->addASNLog('ASN failed', 'asnFailed', 1,
        TimeHelper::getMilliseconds() - $timestampBeforeSend);
      $externalLogs->addErrorLog('Failed to send ASN, PO:' . $requestDto->getPoNumber());


      return false;
    } finally {
      /** @var LogSenderService $logSenderService */
      $logSenderService = pluginApp(LogSenderService::class);
      $logSenderService->execute($externalLogs->getLogs());
    }
  }


  /**
   * Prepare ASN message body.
   *
   * @param Order $order
   *
   * @return RequestDTO | null
   * @throws \Exception
   */
  public function prepareRequestDto(Order $order): RequestDTO
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    try {
      /** @var RequestDTO $requestDto */
      $requestDto = pluginApp(RequestDTO::class);

      $orderId = $order->id;
      $poNumber = $this->orderPropertyService->getCheckedPoNumber($orderId);

      if (!isset($poNumber) || empty($poNumber)) {
        // getCheckedPoNumber does an error-level internal log about not having a PO for the order
        $externalLogs->addErrorLog("The order with ID " . $orderId . " cannot be matched with a Wayfair PO");
        return null;
      }

      $requestDto->setPoNumber($poNumber);

      $purchaseOrderInfo = $this->purchaseOrderService->getPurchaseOrderInfo($poNumber);
      if (!isset($purchaseOrderInfo) || empty($purchaseOrderInfo)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_SHIPPING_CANNOT_GET_PO_DATA), [
              'additionalInfo' => ['orderId' => $orderId],
              'method' => __METHOD__,
              'referenceType' => 'purchaseOrder',
              'referenceValue' => $poNumber
            ]
          );

        $externalLogs->addErrorLog("Unable to get data from Wayfair for PO with ID "
          . $poNumber . '. Order: ' . $orderId);

        return null;
      }

      $wayfairShippingInformation = $purchaseOrderInfo['shippingInfo'];
      if (!isset($wayfairShippingInformation) || empty($wayfairShippingInformation)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_WAYFAIR_MISSING_SHIPPING_INFO), [
              'additionalInfo' => ['orderId' => $orderId],
              'method' => __METHOD__,
              'referenceType' => 'purchaseOrder',
              'referenceValue' => $poNumber
            ]
          );

        $externalLogs->addErrorLog("Unable to get shipping data from Wayfair for PO with ID "
          . $poNumber . '. Order: ' . $orderId);
        return null;
      }

      $plentymarketsShippingInformation = $this->shippingInformationRepositoryContract->getShippingInformationByOrderId($orderId);
      if (!isset($plentymarketsShippingInformation) || empty($plentymarketsShippingInformation)) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_PM_MISSING_SHIPPING_INFO), [
              'additionalInfo' => [
                'orderId' => $orderId,
                'poNumber' => $poNumber
              ],
              'method' => __METHOD__,
              'referenceType' => '$orderId',
              'referenceValue' => $orderId
            ]
          );

        $externalLogs->addErrorLog("Unable to get shipping data from Plentymarkets for Order with ID " . $orderId
          . '. PO: ' . $poNumber);
        return null;
      }

      $this->loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG_ORDER_PURCHASE_ORDER_AND_SHIPPING_INFO), [
          'additionalInfo' => [
            'order' => $order,
            'po' => $purchaseOrderInfo,
            'shippingInformation' => $plentymarketsShippingInformation
          ],
          'method' => __METHOD__
        ]
      );

      $asnTotalWeight = 0.0;
      $asnTotalVolume = 0.0;
      $asnTrackingNumbers = [];
      $products = $purchaseOrderInfo['products'];

      //Decide how to get tracking and package information.
      if ($this->shipmentProviderService->isShippingWithWayfair() || empty($plentymarketsShippingInformation->shippingServiceProvider)) {
        // shipping on wayfair account
        $this->loggerContract->info(
          TranslationHelper::getLoggerKey(self::LOG_KEY_SHIPPING_ON_WAYFAIR), [
            'additionalInfo' => [
              'PoNumber' => $poNumber,
              'order' => $order
            ],
            'method' => __METHOD__
          ]
        );

        $scacCode = $wayfairShippingInformation['carrierCode'];

        $messageForMissingTrackingNumber = sprintf(self::MISSING_TRACKING_NUMBER_FOR_ASN, $orderId, $poNumber);

        try {
          $fetchedTrackingNumbers = $this->fetchShippingLabelContract->getTrackingNumber(ShippingLabelHelper::removePoNumberPrefix($poNumber));
        } catch (\Exception $exception) {
          // TODO: lower to warning if/when warning-level logs are working
          // because the lack of tracking information is not a fatal issue for ASNs,
          // and there are legitimate reasons to send an ASN without the tracking info.
          $this->loggerContract->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_TRACKING_NUMBER_EMPTY_BUT_NOT_REQUIRED_FOR_ASN), [
              'additionalInfo' => [
                'PoNumber' => $poNumber,
                'order' => $order,
                'exception' => $exception,
                'message' => $exception->getMessage(),
                'stacktrace' => $exception->getTrace()
              ],
              'method' => __METHOD__
            ]
          );

          $externalLogs->addWarningLog($messageForMissingTrackingNumber . ' - '
            . get_class($exception) . ': ' . $exception->getMessage());

        }

        // TODO: if/when FetchDocumentService is changed to return tracking numbers instead of Label_Generation_Event_Type, update this logic

        /** @var bool $haveTrackingNumbers */
        $haveTrackingNumbers = isset($fetchedTrackingNumbers) &&
          is_array($fetchedTrackingNumbers) && !empty($fetchedTrackingNumbers);

        if (!$haveTrackingNumbers) {
          // TODO: lower to warning if/when warning-level logs are working
          // because the lack of tracking information is not a fatal issue for ASNs,
          // and there are legitimate reasons to send an ASN without the tracking info.
          $this->loggerContract->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_TRACKING_NUMBER_EMPTY_BUT_NOT_REQUIRED_FOR_ASN), [
              'additionalInfo' => [
                'PoNumber' => $poNumber,
                'order' => $order
              ],
              'method' => __METHOD__
            ]
          );

          $externalLogs->addWarningLog($messageForMissingTrackingNumber);
        }

        foreach ($products as $index => $product) {

          $trackingNum = null;
          if ($haveTrackingNumbers) {
            // TODO: verify that the assumption from v1.1.1 is correct: fetchedTrackingNumbers[$index] is for products[$index]
            if (array_key_exists($index, $fetchedTrackingNumbers)) {
              $shippingEvent = $fetchedTrackingNumbers[$index];
              if (is_array($shippingEvent) && array_key_exists(self::GENERATED_SHIPPING_LABELS, $shippingEvent)) {
                $generatedShippingLabels = $shippingEvent[self::GENERATED_SHIPPING_LABELS];
                if (is_array($generatedShippingLabels) && !empty($generatedShippingLabels)) {
                  // FIXME: v1.1.1 only uses first shipping label, but package may have multiple labels
                  // we need to determine how to associate the tracking numbers with the packages!
                  $label = $generatedShippingLabels[0];

                  if (is_array($label) && array_key_exists(self::TRACKING_NUMBER, $label)) {
                    $trackingNum = $label[self::TRACKING_NUMBER];
                  }
                } else {
                  $externalLogs->addErrorLog('empty generatedShippingLabels element found when getting tracking info' .
                    ' for product ' . $index .
                    ' when shipping on Wayfair account.' . ' Order: ' . $orderId . ' PO: ' . $poNumber);
                }
              } else {
                $externalLogs->addErrorLog('No generatedShippingLabels element found when getting tracking info' .
                  ' for product ' . $index .
                  ' when shipping on Wayfair account.' . ' Order: ' . $orderId . ' PO: ' . $poNumber);
              }
            } else {
              $externalLogs->addErrorLog('No label generation event data found when getting tracking info' .
                ' for product ' . $index .
                ' when shipping on Wayfair account.' . ' Order: ' . $orderId . ' PO: ' . $poNumber);
            }
          }

          if (!isset($trackingNum) || empty($trackingNum)) {
            $externalLogs->addWarningLog('When sending ASN to Wayfair and shipping on Wayfair account,' .
              ' could not determine any tracking numbers for Product '
              . $index . ' for Order ' . $orderId . '. PO: ' . $poNumber);
          }

          $asnTrackingNumbers[] = $trackingNum;
          $asnTotalWeight += (float)$product['totalWeight'];
        }

        $requestDto->setPackageCount(is_array($products) ? count($products) : 1);

      } // end of shipping on Wayfair account
      else {
        //Ship on own account info, get data from PM.
        $this->loggerContract->info(
          TranslationHelper::getLoggerKey(self::LOG_KEY_SHIPPING_ON_OWN_ACCOUNT), [
            'additionalInfo' => [
              'PoNumber' => $poNumber,
              'order' => $order
            ],
            'method' => __METHOD__
          ]
        );

        $scacCode = $this->carrierScacRepository->findScacByCarrierId($plentymarketsShippingInformation->shippingServiceProvider->id);
        $orderShippingPackages = $this->orderShippingPackageRepositoryContract->listOrderShippingPackages($orderId);
        $orderTrackingNumbers = $this->orderRepositoryContract->getPackageNumbers($orderId);
        $requestDto->setPackageCount(count($orderShippingPackages) > 0 ? count($orderShippingPackages) : 1);

        $this->loggerContract->debug(
          TranslationHelper::getLoggerKey(self::LOG_KEY_SHIPPING_ON_OWN_ACCOUNT),
          [
            'additionalInfo' => [
              'PoNumber' => $poNumber,
              'order' => $order,
              'shipping' => $this->shipmentProviderService
            ],
            'method' => __METHOD__
          ]
        );

        /** @var OrderShippingPackage $orderShippingPackage */
        foreach ($orderShippingPackages as $orderShippingPackage) {
          // TODO: determine if packageId is more useful at logging time than id
          $packageId = $orderShippingPackage->id;
          $asnTotalVolume += $orderShippingPackage->volume;

          $trackingNum = $orderShippingPackage->packageNumber;

          if ((!isset($trackingNum) || empty($trackingNum)) &&
            (isset($orderTrackingNumbers) && !empty($orderTrackingNumbers))) {
            // need to try getting tracking numbers from the order itself instead of the package(s).

            $externalLogs->addInfoLog('When sending ASN to Wayfair and shipping on own account,' .
              ' Package ' . $packageId . ' for Order ' . $orderId .
              ' does not have a tracking number on the orderShippingPackage object.' .
              ' Falling back to tracking numbers from Order.' . ' PO: ' . $poNumber);

            // FIXME: v1.1.1 is only using 0th element of $orderTrackingNumbers!
            //  this is wrong if there's more than one element in orderTrackingNumbers
            $trackingNum = $orderTrackingNumbers[0];
          }

          if (!isset($trackingNum) || empty($trackingNum)) {
            // there are no tracking numbers related to the package

            $externalLogs->addWarningLog('When sending ASN to Wayfair and shipping on own account,' .
              ' could not determine any tracking numbers for Package '
              . $packageId . ' for Order ' . $orderId . '. PO: ' . $poNumber);
          }

          $asnTrackingNumbers[] = $trackingNum;

          $asnTotalWeight += (float)$orderShippingPackage->weight;
        }
      } // End of ship on own account

      $packages = $this->buildASNPackageInfo($products, $asnTrackingNumbers);

      $requestDto->setCarrierCode($scacCode);
      $requestDto->setSupplierId($purchaseOrderInfo['warehouse']['id']);
      $requestDto->setSmallParcelShipments($packages);
      $requestDto->setTrackingNumber(implode(',', $asnTrackingNumbers));
      $requestDto->setWeight($asnTotalWeight);
      $requestDto->setVolume($asnTotalVolume);

      $requestDto->setShipSpeed($wayfairShippingInformation['shipSpeed']);
      $requestDto->setShipDate($plentymarketsShippingInformation->shipmentAt);

      $plentyBillingAddress = $order->billingAddress;
      if (!isset($plentyBillingAddress)) {
        $this->loggerContract->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_PM_MISSING_BILLING_INFO), [
            'additionalInfo' => [
              'PoNumber' => $poNumber,
              'order' => $order
            ],
            'method' => __METHOD__,
            'referenceType' => '$orderId',
            'referenceValue' => $orderId,
          ]
        );

        $externalLogs->addErrorLog('Plentymarkets is missing billing information for Order ' . $orderId
          . '. PO: ' . $poNumber);

        return null;
      }

      $wayfairSourceAddress = $this->mapAddress($plentyBillingAddress);
      $requestDto->setSourceAddress($wayfairSourceAddress);

      if (empty($requestDto->getSourceAddress()->getPostalCode())) {
        // FIXME: defaulting to this postal code without knowing it is appropriate.
        $externalLogs->addInfoLog("Defaulting to package source postal code of " . BillingAddress::POSTCODE);
        $requestDto->getSourceAddress()->setPostalCode(BillingAddress::POSTCODE);
      }

      $plentyDeliveryAddress = $order->deliveryAddress;
      if (!isset($plentyDeliveryAddress)) {
        $this->loggerContract->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_PM_MISSING_DELIVERY_ADDRESS), [
            'additionalInfo' => [
              'PoNumber' => $poNumber,
              'order' => $order
            ],
            'method' => __METHOD__,
            'referenceType' => '$orderId',
            'referenceValue' => $orderId,
          ]
        );

        return null;
      }

      $wayfairDestinationAddress = $this->mapAddress($plentyDeliveryAddress);
      $requestDto->setDestinationAddress($wayfairDestinationAddress);

      $this->loggerContract->info(
        TranslationHelper::getLoggerKey('finishedPreparedDto'), [
          'additionalInfo' => $requestDto,
          'method' => __METHOD__
        ]
      );

      return $requestDto;
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }

  /**
   * Log to OrderASN table that this order has been sent an ASN to WF.
   *
   * @param Order $order
   */
  public function logASNSentRecord(Order $order)
  {
    $data = ['orderId' => $order->id];

    $this->orderASNRepository->createOrUpdate($data);
  }

  /**
   * Check if an order has already been sent ASN record to WF.
   *
   * @param Order $order
   *
   * @return bool
   */
  public function isOrderSentASN(Order $order): bool
  {
    $record = $this->orderASNRepository->findByOrderId($order->id);

    $this->loggerContract
      ->debug(
        TranslationHelper::getLoggerKey('checkIfASNSent'), [
          'additionalInfo' => ['orderId' => $order->id, 'record' => $record],
          'method' => __METHOD__
        ]
      );

    // caller will log result
    return !empty($record);
  }

  /**
   * Convert Plentymarkets address to shipping address.
   *
   * @param Address $address
   *
   * @return ShipNoticeAddressDTO
   */
  private function mapAddress(Address $address): ShipNoticeAddressDTO
  {
    /** @var ShipNoticeAddressDTO $addressDto */
    $addressDto = pluginApp(ShipNoticeAddressDTO::class);
    $addressDto->setName($address->name1);
    $addressDto->setStreetAddress1($address->address1);
    $addressDto->setStreetAddress2($address->address2);
    $addressDto->setCity($address->town);
    $addressDto->setState($address->state->isoCode);
    $addressDto->setPostalCode($address->postalCode);
    $addressDto->setCountry($address->country->isoCode2);

    return $addressDto;
  }

  /**
   * Build a set of package info objects based on product information and tracking numbers
   * @param array $products
   * @param array $trackingNumbers
   *
   * @return array
   */
  private function buildASNPackageInfo(array $products, array $trackingNumbers): array
  {
    // TODO: verify that the assumption is correct: $trackingNumbers[i] is for $products[i]
    $packages = [];
    foreach ($products as $index => $product) {
      // TODO: validate information pulled out of $product
      $packages[] = [
        'package' => [
          'code' => [
            'type' => 'TRACKING_NUMBER',
            'value' => empty($trackingNumbers[$index]) ? '' : $trackingNumbers[$index]
          ],
          'weight' => $product['totalWeight']
        ],
        'items' => [
          [
            'partNumber' => $product['partNumber'],
            'quantity' => $product['quantity']
          ]
        ]
      ];
    }

    return $packages;
  }
}
