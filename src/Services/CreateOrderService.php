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
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\BillingAddress;
use Wayfair\Helpers\PaymentHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Mappers\AddressMapper;
use Wayfair\Mappers\PendingPurchaseOrderMapper;
use Wayfair\Mappers\PurchaseOrderMapper;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
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

  /**
   * @var PurchaseOrderMapper
   */
  public $purchaseOrderMapper;

  /**
   * @var AddressMapper
   */
  public $addressMapper;

  /**
   * @var OrderRepositoryContract
   */
  public $orderRepositoryContract;

  /**
   * @var KeyValueRepository
   */
  public $keyValueRepository;

  /**
   * @var WarehouseSupplierRepository
   */
  public $warehouseSupplierRepository;

  /**
   * @var PaymentRepositoryContract
   */
  public $paymentRepositoryContract;

  /**
   * @var PaymentHelper
   */
  public $paymentHelper;

  /**
   * @var PaymentOrderRelationRepositoryContract
   */
  public $paymentOrderRelationRepositoryContract;

  /**
   * @var PendingPurchaseOrderMapper
   */
  public $pendingPurchaseOrderMapper;

  /**
   * @var PendingOrdersRepository
   */
  public $pendingOrdersRepository;

  /**
   * @var SavePackingSlipService
   */
  private $savePackingSlipService;

  /**
   * @var AddressService
   */
  private $addressService;

  /**
   * CreateOrderService constructor.
   *
   * @param PurchaseOrderMapper $purchaseOrderMapper
   * @param AddressMapper $addressMapper
   * @param OrderRepositoryContract $orderRepositoryContract
   * @param KeyValueRepository $keyValueRepository
   * @param WarehouseSupplierRepository $warehouseSupplierRepository
   * @param PaymentRepositoryContract $paymentRepositoryContract
   * @param PaymentHelper $paymentHelper
   * @param PaymentOrderRelationRepositoryContract $paymentOrderRelationRepositoryContract
   * @param PendingPurchaseOrderMapper $pendingPurchaseOrderMapper
   * @param PendingOrdersRepository $pendingOrdersRepository
   * @param SavePackingSlipService $savePackingSlipService
   * @param AddressService $addressService
   */
  public function __construct(
    PurchaseOrderMapper $purchaseOrderMapper,
    AddressMapper $addressMapper,
    OrderRepositoryContract $orderRepositoryContract,
    KeyValueRepository $keyValueRepository,
    WarehouseSupplierRepository $warehouseSupplierRepository,
    PaymentRepositoryContract $paymentRepositoryContract,
    PaymentHelper $paymentHelper,
    PaymentOrderRelationRepositoryContract $paymentOrderRelationRepositoryContract,
    PendingPurchaseOrderMapper $pendingPurchaseOrderMapper,
    PendingOrdersRepository $pendingOrdersRepository,
    SavePackingSlipService $savePackingSlipService,
    AddressService $addressService
  ) {
    $this->purchaseOrderMapper = $purchaseOrderMapper;
    $this->addressMapper = $addressMapper;
    $this->orderRepositoryContract = $orderRepositoryContract;
    $this->keyValueRepository = $keyValueRepository;
    $this->warehouseSupplierRepository = $warehouseSupplierRepository;
    $this->paymentRepositoryContract = $paymentRepositoryContract;
    $this->paymentHelper = $paymentHelper;
    $this->paymentOrderRelationRepositoryContract = $paymentOrderRelationRepositoryContract;
    $this->pendingPurchaseOrderMapper = $pendingPurchaseOrderMapper;
    $this->pendingOrdersRepository = $pendingOrdersRepository;
    $this->savePackingSlipService = $savePackingSlipService;
    $this->addressService = $addressService;
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
   * @param ResponseDTO $dto
   *
   * @return int
   * @throws \Exception
   */
  public function create(ResponseDTO $dto): int
  {
    /**
     * @var AbstractConfigHelper $configHelper
     */
    $configHelper = pluginApp(AbstractConfigHelper::class);

    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    /** @var int $orderId */
    $orderId = 0;
    /** @var string $poNumber */
    $poNumber = '';

    try {
      if (!isset($dto)) {
        throw new \Exception("Cannot create order - no PO information provided");
      }

      // Get referrer ID
      $referrerId = $configHelper->getOrderReferrerValue();

      if (!isset($referrerId) || $referrerId <= 0) {
        throw new \Exception("Cannot create order - no referrer value");
      }

      $poNumber = $dto->getPoNumber();
      if (!isset($poNumber) || empty($poNumber)) {
        throw new \Exception("Cannot create order - no PO number provided");
      }

      // Check if order already exists
      $this->orderRepositoryContract->setFilters(['externalOrderId' => $poNumber]);
      // not expecting more than one page, so not setting page filter
      $orderList = $this->orderRepositoryContract->searchOrders();

      $numberOfOrdersForPO = $orderList->getTotalCount();
      if ($numberOfOrdersForPO > 0) {
        // orders exist for the Wayfair PO. Do not create another one.
        $loggerContract->warning(TranslationHelper::getLoggerKey(self::LOG_KEY_ORDERS_ALREADY_EXIST), [
          'additionalInfo' => ['poNumber' => $poNumber, 'orders' => $orderList->getResult()],
          'method' => __METHOD__
        ]);

        $externalLogs->addInfoLog("Order creation skipped - found " . $numberOfOrdersForPO . " already created for PO " . $poNumber);

        // make sure that we (re)accept this order,
        // as the presence of the Plentymarkets Order means it should no longer be "open."
        $this->createPendingOrder($dto);

        return -1;
      }

      $loggerContract->info(TranslationHelper::getLoggerKey(self::LOG_KEY_CREATING_ORDER), [
        'additionalInfo' => [
          'poNumber' => $poNumber
        ],
        'method' => __METHOD__
      ]);

      // Get payment method id
      // Create billing address and delivery address
      $addressDTO = AddressDTO::createFromArray(BillingAddress::BillingAddressAsArray);

      $billing = null;
      $billingInfoFromDTO = $dto->getBillingInfo();
      $encodedBillingContactFromRepository = $this->keyValueRepository->get(AbstractConfigHelper::BILLING_CONTACT);
      if (isset($encodedBillingContactFromRepository) && !empty($encodedBillingContactFromRepository)) {
        try {
          $billing = \json_decode($encodedBillingContactFromRepository, true);
        } catch (\Exception $e) {
          $externalLogs->addWarningLog("Could not decode billing information in KeyValueRepository - "
            . get_class($e) . ": " . $e->getMessage(), $e->getTraceAsString());
        }
      }

      // TODO: verify that supplier's billing info should take precedence over billing info from PO here
      if (!isset($billing) or empty($billing)) {
        if (!isset($billingInfoFromDTO) || empty($billingInfoFromDTO)) {
          throw new \Exception("No billing info provided in argument, nor any billing info in repository");
        }

        $billing = $this->addressService->createContactAndAddress($addressDTO, $billingInfoFromDTO, $referrerId, ContactType::TYPE_PARTNER, AddressRelationType::BILLING_ADDRESS);
        $this->keyValueRepository->put(AbstractConfigHelper::BILLING_CONTACT, \json_encode($billing));
      }

      if (!isset($billingInfoFromDTO) || empty($billingInfoFromDTO)) {
        throw new \Exception("Purchase order information is missing billing details. PO: " . $poNumber);
      }

      $shipTo = $dto->getShipTo();

      if (!isset($shipTo)) {
        throw new \Exception("Purchase order information is missing destination shipping information. PO: "
          . $poNumber);
      }

      // delivery always uses billing info from PO instead of the information stored in the system
      $delivery = $this->addressService->createContactAndAddress($shipTo, $billingInfoFromDTO, $referrerId, ContactType::TYPE_CUSTOMER, AddressRelationType::DELIVERY_ADDRESS);

      if (!isset($delivery) || empty($delivery)) {
        throw new \Exception("Unable to create delivery information for purchase order: " . $poNumber);
      }

      $warehouse = $dto->getWarehouse();
      if (!isset($warehouse)) {
        throw new \Exception("No warehouse information for PO " . $poNumber);
      }

      $supplierID = $warehouse->getId();

      if (!isset($supplierID)) {
        throw new \Exception("PO " . $poNumber . " contains Warehouse information that is missing an ID.");
      }

      $plentyWarehouseId = $this->warehouseSupplierRepository->findBySupplierId($supplierID);
      if (!isset($plentyWarehouseId) || empty($plentyWarehouseId)) {
        throw new \Exception("Could not find Warehouse ID for PO " . $poNumber . " for supplier " . $supplierID);
      }

      $orderData = $this->purchaseOrderMapper->map(
        $dto,
        $billing['addressId'],
        $billing['contactId'],
        $delivery['addressId'],
        $referrerId,
        $plentyWarehouseId,
        (string) AbstractConfigHelper::PAYMENT_METHOD_INVOICE
      );

      if (!isset($orderData) || empty($orderData)) {
        throw new \Exception("Unable to map order data for PO " . $poNumber);
      }

      $order = $this->orderRepositoryContract->createOrder($orderData);
      if (!isset($order) or !$order->id) {
        throw new \Exception("Unable to create new order using order data: " . \json_encode($orderData));
      }

      $orderId = $order->id;

      /*
       * The search at the beginning of this function will find the order that was just created,
       *  and block creation of another order for this Wayfair PO.
       */

      if (!$this->createPendingOrder($dto)) {
        throw new \Exception("Unable to create pending order entry for order " . $order . " PO: " . $poNumber);
      }

      // Create payment
      // NOTICE: there is no way to undo the payment actions
      $payment = $this->createPayment($poNumber);
      if (!isset($payment) || !$payment->id) {
        throw new \Exception("Unable to create payment information for order " . $order . " PO: " . $poNumber);
      }

      $paymentID = $payment->id;

      // Create order payment relation
      $paymentRelation = $this->paymentOrderRelationRepositoryContract->createOrderRelation($payment, $order);
      if (!isset($paymentRelation) || !$paymentRelation->id) {
        throw new \Exception("Unable to relate payment " . $paymentID . " with order " . $orderId);
      }

      // packing slips are an optional feature and may be phased out.
      // do not fail the order for lack of packing slip.
      try {
        $this->savePackingSlipService->save($orderId, $poNumber);
      } catch (\Exception $exception) {
        $externalLogs->addErrorLog("Unexpected " . get_class($exception) . " while working with packing slips: " . $exception->getMessage(), $exception->getTraceAsString());
      }

      return $orderId;
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }

  /**
   * Create a Pending Order record for a Purchase Order,
   * if not already existing.
   * @param ResponseDTO $dto
   *
   * @return void
   */
  private function createPendingOrder(ResponseDTO $dto): bool
  {
    $poNumber = $dto->getPoNumber();
    $pendingOrder = null;
    try {
      $pendingOrder = $this->pendingOrdersRepository->get($poNumber);
    } catch (\Exception $e) {
      /** @var LoggerContract $loggerContract */
      $loggerContract = pluginApp(LoggerContract::class);

      $loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_PENDING_ORDER_MAY_EXIST), [
        'exception' => $e,
        'exceptionType' => get_class($e),
        'exceptionMessage' => $e->getMessage(),
        'additionalInfo' => ['poNumber' => $poNumber],
        'method' => __METHOD__
      ]);
    }

    if (isset($pendingOrder)) {
      // PO already queued for acceptance
      return true;
    }

    $pendingOrder = $this->pendingPurchaseOrderMapper->map($dto);
    return $this->pendingOrdersRepository->insert($pendingOrder);
  }

  /**
   * @param string $poNumber
   *
   * @return Payment
   */
  private function createPayment(string $poNumber): Payment
  {
    $data = [
      'amount' => 0,
      'mopId' => AbstractConfigHelper::PAYMENT_METHOD_INVOICE,
      'status' => Payment::STATUS_APPROVED,
      'transactionType' => AbstractConfigHelper::PAYMENT_TRANSACTION_TYPE_BOOKED_PAYMENT,
      'properties' => [
        [
          'typeId' => PaymentProperty::TYPE_TRANSACTION_ID,
          'value' => $poNumber . '_' . time() . '_' . AbstractConfigHelper::PAYMENT_KEY
        ]
      ]
    ];

    return $this->paymentRepositoryContract->createPayment($data);
  }
}
