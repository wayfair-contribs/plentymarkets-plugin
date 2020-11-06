<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Plenty\Modules\Account\Contact\Models\ContactType;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\General\AddressDTO;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
use Wayfair\Core\Exceptions\CreateOrderException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\BillingAddress;
use Wayfair\Helpers\PaymentHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Mappers\AddressMapper;
use Wayfair\Mappers\PendingPurchaseOrderMapper;
use Wayfair\Mappers\PurchaseOrderMapper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Wayfair\Core\Dto\General\BillingInfoDTO;
use Wayfair\Models\ExternalLogs;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Repositories\PendingOrdersRepository;
use Wayfair\Repositories\WarehouseSupplierRepository;

/**
 * Class CreateOrderService
 *
 * Other modules use the 'create' method in order introduce a new plentymarkets Order for a Wayfair PO.
 * @package Wayfair\Services
 */
class CreateOrderService
{
  const LOG_KEY_ORDERS_ALREADY_EXIST = 'createOrderAlreadyExists';
  const LOG_KEY_CREATING_ORDER = 'creatingNewOrder';
  const LOG_KEY_PENDING_ORDER_MAY_EXIST = 'pendingOrderMayExist';

  const RETURN_VALUE_EXISTING_ORDERS = -1;

  /**
   * @var PurchaseOrderMapper
   */
  private $wfPurchaseOrderMapper;

  /**
   * @var AddressMapper
   */
  private $wfAddressMapper;

  /**
   * @var OrderRepositoryContract
   */
  private $plentyOrderRepositoryContract;

  /**
   * @var KeyValueRepository
   */
  private $wfKeyValueRepository;

  /**
   * @var WarehouseSupplierRepository
   */
  private $wfWarehouseSupplierRepository;

  /**
   * @var PaymentRepositoryContract
   */
  private $plentyPaymentRepositoryContract;

  /**
   * @var PaymentHelper
   */
  private $wfPaymentHelper;

  /**
   * @var PaymentOrderRelationRepositoryContract
   */
  private $plentyPaymentOrderRelationRepositoryContract;

  /**
   * @var PendingPurchaseOrderMapper
   */
  private $wfPendingPurchaseOrderMapper;

  /**
   * @var PendingOrdersRepository
   */
  private $wfPendingOrdersRepository;

  /**
   * @var SavePackingSlipService
   */
  private $wfSavePackingSlipService;

  /**
   * @var AddressService
   */
  private $wfAddressService;

  /**
   * @var AbstractConfigHelper
   */
  private $wfConfigHelper;

  /**
   * @var LoggerContract
   */
  private $wfLogger;

  /**
   * @var LogSenderService
   */
  private $wfLogSenderService;

  /**
   * CreateOrderService constructor.
   *
   * @param PurchaseOrderMapper $wfPurchaseOrderMapper
   * @param AddressMapper $wfAddressMapper
   * @param OrderRepositoryContract $plentyOrderRepositoryContract
   * @param KeyValueRepository $wfKeyValueRepository
   * @param WarehouseSupplierRepository $wfWarehouseSupplierRepository
   * @param PaymentRepositoryContract $plentyPaymentRepositoryContract
   * @param PaymentHelper $wfPaymentHelper
   * @param PaymentOrderRelationRepositoryContract $plentyPaymentOrderRelationRepositoryContract
   * @param PendingPurchaseOrderMapper $wfPendingPurchaseOrderMapper
   * @param PendingOrdersRepository $wfPendingOrdersRepository
   * @param SavePackingSlipService $wfSavePackingSlipService
   * @param AddressService $wfAddressService
   * @param AbstractConfigHelper $wfConfigHelper
   * @param LoggerContract $wfLogger
   * @param LogSenderService $wfLogSenderService
   */
  public function __construct(
    PurchaseOrderMapper $wfPurchaseOrderMapper,
    AddressMapper $wfAddressMapper,
    OrderRepositoryContract $plentyOrderRepositoryContract,
    KeyValueRepository $wfKeyValueRepository,
    WarehouseSupplierRepository $wfWarehouseSupplierRepository,
    PaymentRepositoryContract $plentyPaymentRepositoryContract,
    PaymentHelper $wfPaymentHelper,
    PaymentOrderRelationRepositoryContract $plentyPaymentOrderRelationRepositoryContract,
    PendingPurchaseOrderMapper $wfPendingPurchaseOrderMapper,
    PendingOrdersRepository $wfPendingOrdersRepository,
    SavePackingSlipService $wfSavePackingSlipService,
    AddressService $wfAddressService,
    AbstractConfigHelper $wfConfigHelper,
    LoggerContract $wfLogger,
    LogSenderService $wfLogSenderService
  ) {
    $this->wfPurchaseOrderMapper = $wfPurchaseOrderMapper;
    $this->wfAddressMapper = $wfAddressMapper;
    $this->plentyOrderRepositoryContract = $plentyOrderRepositoryContract;
    $this->wfKeyValueRepository = $wfKeyValueRepository;
    $this->wfWarehouseSupplierRepository = $wfWarehouseSupplierRepository;
    $this->plentyPaymentRepositoryContract = $plentyPaymentRepositoryContract;
    $this->wfPaymentHelper = $wfPaymentHelper;
    $this->plentyPaymentOrderRelationRepositoryContract = $plentyPaymentOrderRelationRepositoryContract;
    $this->wfPendingPurchaseOrderMapper = $wfPendingPurchaseOrderMapper;
    $this->wfPendingOrdersRepository = $wfPendingOrdersRepository;
    $this->wfSavePackingSlipService = $wfSavePackingSlipService;
    $this->wfAddressService = $wfAddressService;
    $this->wfConfigHelper = $wfConfigHelper;
    $this->wfLogger = $wfLogger;
    $this->wfLogSenderService = $wfLogSenderService;
  }

  /**
   * Create a Plentymarkets order
   * based on:
   *  - Wayfair data in input parameter
   *  - Wayfair data from APIs (packing slips, etc)
   *  - Stored values in the plentymarkets system
   *
   * If an order already exists for the Wayfair PO, returns a negative number.
   * If an order cannot be created, returns 0.
   * Otherwise, attempts to create all Plentymarkets artifacts for an order, then creates the order and returns the Order's positive ID
   *
   * If the order cannot be created for any reason, throws an Exception.
   *
   * @param ResponseDTO $wfPurchaseOrderResponseDTO
   *
   * @return int
   * @throws CreateOrderException
   */
  public function create(ResponseDTO $wfPurchaseOrderResponseDTO): int
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    /** @var int $plentyOrderId */
    $plentyOrderId = 0;
    /** @var string $wfPurchaseOrderNumber */
    $wfPurchaseOrderNumber = '';

    try {
      if (!isset($wfPurchaseOrderResponseDTO)) {
        throw new CreateOrderException("Cannot create order - no PO information provided");
      }

      $wfPurchaseOrderNumber = $wfPurchaseOrderResponseDTO->getPoNumber();
      if (!isset($wfPurchaseOrderNumber) || empty(trim($wfPurchaseOrderNumber))) {
        throw new CreateOrderException("Cannot create order - no PO number provided");
      }

      // Get referrer ID
      $referrerIdForWayfair  = $this->wfConfigHelper->getOrderReferrerValue();

      if (!isset($referrerIdForWayfair) || $referrerIdForWayfair  <= 0) {
        throw new CreateOrderException("Cannot create order - no referrer value");
      }

      $idsOfExistingPlentyOrders = $this->getIdsOfExistingPlentyOrders($wfPurchaseOrderNumber);
      $numberOfPlentyOrdersForWfPurchaseOrder = sizeof($idsOfExistingPlentyOrders);

      if ($numberOfPlentyOrdersForWfPurchaseOrder > 0) {
        // orders exist for the Wayfair PO. Do not create another one.
        $this->wfLogger->warning(TranslationHelper::getLoggerKey(self::LOG_KEY_ORDERS_ALREADY_EXIST), [
          'additionalInfo' => [
            'poNumber' => $wfPurchaseOrderNumber,
            'order_ids' => json_encode($idsOfExistingPlentyOrders)
          ],
          'method' => __METHOD__
        ]);

        $externalLogs->addInfoLog("Order creation skipped - found " . $numberOfPlentyOrdersForWfPurchaseOrder . " already created for PO " . $wfPurchaseOrderNumber);

        // make sure that we (re)accept this order,
        // as the presence of the Plentymarkets Order means it should no longer be "open."
        $this->createPendingOrder($wfPurchaseOrderResponseDTO);

        return self::RETURN_VALUE_EXISTING_ORDERS;
      }

      $this->wfLogger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_CREATING_ORDER), [
        'additionalInfo' => [
          'poNumber' => $wfPurchaseOrderNumber
        ],
        'method' => __METHOD__
      ]);

      // Create billing address and delivery address
      $wfAddressDto = AddressDTO::createFromArray(BillingAddress::BillingAddressAsArray);

      $billingInfoFromDto = $wfPurchaseOrderResponseDTO->getBillingInfo();

      if (!isset($billingInfoFromDto) || empty($billingInfoFromDto)) {
        throw new CreateOrderException("Purchase order information is missing billing details. PO: " . $wfPurchaseOrderNumber);
      }

      $billingInformationForWayfair = $this->getOrCreateBillingInfoForWayfair($wfAddressDto, $billingInfoFromDto, $referrerIdForWayfair, $externalLogs);

      if (!isset($billingInformationForWayfair) || empty($billingInformationForWayfair)) {
        throw new CreateOrderException("Could not determine information on how to bill Wayfair for PO: " . $wfPurchaseOrderNumber);
      }

      /** @var int */
      $wayfairBillingAddressId = null;

      if (key_exists('addressId', $billingInformationForWayfair)) {
        $wayfairBillingAddressId = $billingInformationForWayfair['addressId'];
      }

      if (!isset($wayfairBillingAddressId) || $wayfairBillingAddressId < 1) {
        throw new CreateOrderException("Could not determine Wayfair Billing Address ID for PO: " . $wfPurchaseOrderNumber);
      }

      /** @var int */
      $wayfairBillingContactId = null;
      if (key_exists('contactId', $billingInformationForWayfair)) {
        $wayfairBillingContactId = $billingInformationForWayfair['contactId'];
      }

      if (!isset($wayfairBillingContactId) || $wayfairBillingContactId < 1) {
        throw new CreateOrderException("Could not determine Wayfair Contact Information ID for PO: " . $wfPurchaseOrderNumber);
      }

      $shipToInWayfairDto = $wfPurchaseOrderResponseDTO->getShipTo();

      if (!isset($shipToInWayfairDto)) {
        throw new CreateOrderException("Purchase order information is missing destination shipping information. PO: "
          . $wfPurchaseOrderNumber);
      }

      // delivery always uses billing info from PO instead of the information stored in the system
      $deliveryInformation = $this->wfAddressService->createContactAndAddress($shipToInWayfairDto, $billingInfoFromDto, $referrerIdForWayfair, ContactType::TYPE_CUSTOMER, AddressRelationType::DELIVERY_ADDRESS);

      if (!isset($deliveryInformation) || empty($deliveryInformation)) {
        throw new CreateOrderException("Unable to create delivery information for purchase order: " . $wfPurchaseOrderNumber);
      }

      /** @var int */
      $deliveryAddressId = $deliveryInformation['addressId'];

      if (!isset($deliveryAddressId) || $deliveryAddressId < 1) {
        throw new CreateOrderException("Could not determine Delivery Address ID for PO: " . $wfPurchaseOrderNumber);
      }

      $warehouseInDto = $wfPurchaseOrderResponseDTO->getWarehouse();
      if (!isset($warehouseInDto)) {
        throw new CreateOrderException("No warehouse information for PO " . $wfPurchaseOrderNumber);
      }

      $supplierIdInDto = $warehouseInDto->getId();

      if (!isset($supplierIdInDto) || empty(trim($supplierIdInDto))) {
        throw new CreateOrderException("PO " . $wfPurchaseOrderNumber . " contains Warehouse information that is missing an ID.");
      }

      // TODO: filter warehouses based on criteria such as stock amounts?
      $potentialIds = $this->wfWarehouseSupplierRepository->findWarehouseIds($supplierIdInDto);

      if (!isset($potentialIds) || empty($potentialIds) || empty(trim($potentialIds[0]))) {
        throw new CreateOrderException("Could not find Warehouse ID for PO " . $wfPurchaseOrderNumber . " for supplier " . $supplierIdInDto);
      }

      $plentyWarehouseIdFromRepo = $potentialIds[0];

      $orderData = $this->wfPurchaseOrderMapper->map(
        $wfPurchaseOrderResponseDTO,
        $wayfairBillingAddressId,
        $wayfairBillingContactId,
        $deliveryAddressId,
        $referrerIdForWayfair,
        $plentyWarehouseIdFromRepo,
        (string) AbstractConfigHelper::PAYMENT_METHOD_INVOICE
      );

      if (!isset($orderData) || empty($orderData)) {
        throw new CreateOrderException("Unable to map order data for PO " . $wfPurchaseOrderNumber);
      }

      $plentyOrder = $this->plentyOrderRepositoryContract->createOrder($orderData);
      if (!isset($plentyOrder) || null == $plentyOrder->id || $plentyOrder->id < 1) {
        throw new CreateOrderException("Unable to create new order using order data: " . \json_encode($orderData));
      }

      $plentyOrderId = $plentyOrder->id;

      /*
       * The search at the beginning of this function will find the order that was just created,
       *  and block creation of another order for this Wayfair PO.
       */

      if (!$this->createPendingOrder($wfPurchaseOrderResponseDTO)) {
        throw new CreateOrderException("Unable to create pending order entry for order " . $plentyOrderId . " PO: " . $wfPurchaseOrderNumber);
      }

      // Create payment
      // NOTICE: there is no way to undo the payment actions
      $plentyPayment = $this->createPayment($wfPurchaseOrderNumber);
      if (!isset($plentyPayment) || null == $plentyPayment->id || $plentyPayment->id < 1) {
        throw new CreateOrderException("Unable to create payment information for order " . $plentyOrderId . " PO: " . $wfPurchaseOrderNumber);
      }

      $plentyPaymentId = $plentyPayment->id;

      // Create order payment relation
      $plentyPaymentRelation = $this->plentyPaymentOrderRelationRepositoryContract->createOrderRelation($plentyPayment, $plentyOrder);
      if (!isset($plentyPaymentRelation) || null == $plentyPaymentRelation->id || $plentyPaymentRelation->id < 1) {
        throw new CreateOrderException("Unable to relate payment " . $plentyPaymentId . " with order " . $plentyOrderId);
      }

      // packing slips are an optional feature and may be phased out.
      // do NOT fail the order for lack of packing slip!
      try {
        $this->wfSavePackingSlipService->save($plentyOrderId, $wfPurchaseOrderNumber);
        // ignore a null/empty result here, as it is not worth the traffic to notify anyone about this.
      } catch (\Exception $exception) {
        $externalLogs->addErrorLog("Unexpected " . get_class($exception) . " while working with packing slips: " . $exception->getMessage(), $exception->getTraceAsString());
      }

      return $plentyOrderId;
    } finally {
      if (count($externalLogs->getLogs())) {
        $this->wfLogSenderService->execute($externalLogs->getLogs());
      }
    }
  }

  /**
   * Create a Pending Order record for a Purchase Order,
   * if not already existing.
   * @param ResponseDTO $wfPurchaseOrderResponseDTO
   *
   * @return void
   */
  function createPendingOrder(ResponseDTO $wfPurchaseOrderResponseDTO): bool
  {
    $wfPurchaseOrderNumber = $wfPurchaseOrderResponseDTO->getPoNumber();
    $pendingOrder = null;
    try {
      $pendingOrder = $this->wfPendingOrdersRepository->get($wfPurchaseOrderNumber);
    } catch (\Exception $e) {

      $this->wfLogger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_PENDING_ORDER_MAY_EXIST), [
        'exception' => $e,
        'exceptionType' => get_class($e),
        'exceptionMessage' => $e->getMessage(),
        'additionalInfo' => ['poNumber' => $wfPurchaseOrderNumber],
        'method' => __METHOD__
      ]);
    }

    if (isset($pendingOrder)) {
      // PO already queued for acceptance
      return true;
    }

    $pendingOrder = $this->wfPendingPurchaseOrderMapper->map($wfPurchaseOrderResponseDTO);
    return $this->wfPendingOrdersRepository->insert($pendingOrder);
  }

  /**
   * @param string $wfPurchaseOrderNumber
   *
   * @return Payment
   */
  function createPayment(string $wfPurchaseOrderNumber): Payment
  {
    $data = [
      'amount' => 0,
      'mopId' => AbstractConfigHelper::PAYMENT_METHOD_INVOICE,
      'status' => Payment::STATUS_APPROVED,
      'transactionType' => AbstractConfigHelper::PAYMENT_TRANSACTION_TYPE_BOOKED_PAYMENT,
      'properties' => [
        [
          'typeId' => PaymentProperty::TYPE_TRANSACTION_ID,
          'value' => $wfPurchaseOrderNumber . '_' . time() . '_' . AbstractConfigHelper::PAYMENT_KEY
        ]
      ]
    ];

    return $this->plentyPaymentRepositoryContract->createPayment($data);
  }

  function getIdsOfExistingPlentyOrders($wfPurchaseOrderNumber): array
  {
    if (!isset($wfPurchaseOrderNumber) || empty(trim($wfPurchaseOrderNumber))) {
      return [];
    }

    $oldFilters = $this->plentyOrderRepositoryContract->getFilters();
    try {
      $this->plentyOrderRepositoryContract->setFilters(['externalOrderId' => $wfPurchaseOrderNumber]);
      // not expecting more than one page, so not setting page filter
      $orderList = $this->plentyOrderRepositoryContract->searchOrders();
    } finally {
      if (isset($oldFilters)) {
        $this->plentyOrderRepositoryContract->setFilters($oldFilters);
      } else {
        $this->plentyOrderRepositoryContract->clearFilters();
      }
    }

    $ids = [];

    foreach ($orderList->getResult() as $key => $order) {
      if (key_exists('id', $order)) {
        $ids[] = $order['id'];
      }
    }

    return $ids;
  }

  /**
   * Cache billing info for Wayfair, if it does not already exist
   *
   * @param AddressDTO $addressDTO
   * @param BillingInfoDTO $billingInfoFromWayfairPurchaseOrderDto
   * @param float $referrerIdForWayfair
   * @param ExternalLogs $externalLogs
   * @return array
   */
  function getOrCreateBillingInfoForWayfair($addressDTO, $billingInfoFromWayfairPurchaseOrderDto, $referrerIdForWayfair, $externalLogs = null): array
  {
    $wfBilling = [];

    $encodedBillingContactFromRepository = $this->wfKeyValueRepository->get(AbstractConfigHelper::BILLING_CONTACT);
    if (isset($encodedBillingContactFromRepository) && !empty($encodedBillingContactFromRepository)) {
      try {
        $wfBilling = \json_decode($encodedBillingContactFromRepository, true);
      } catch (\Exception $e) {
        if (isset($externalLogs)) {
          $externalLogs->addWarningLog("Could not decode billing information in KeyValueRepository - "
            . get_class($e) . ": " . $e->getMessage(), $e->getTraceAsString());
        }
      }
    }

    if (!isset($wfBilling) or empty($wfBilling)) {
      // no billing info cached for Wayfair yet
      if (!isset($billingInfoFromWayfairPurchaseOrderDto) || empty($billingInfoFromWayfairPurchaseOrderDto)) {
        return [];
      }

      $wfBilling = $this->wfAddressService->createContactAndAddress($addressDTO, $billingInfoFromWayfairPurchaseOrderDto, $referrerIdForWayfair, ContactType::TYPE_PARTNER, AddressRelationType::BILLING_ADDRESS);
      $this->wfKeyValueRepository->put(AbstractConfigHelper::BILLING_CONTACT, \json_encode($wfBilling));
    }

    return $wfBilling;
  }
}
