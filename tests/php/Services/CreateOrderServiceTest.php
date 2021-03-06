<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

$plentymocketsFactoriesDirPath = dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Factories' . DIRECTORY_SEPARATOR;

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Helpers' . DIRECTORY_SEPARATOR . 'MockPluginApp.php');

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Overrides' . DIRECTORY_SEPARATOR . 'ReplacePluginApp.php');

require_once($plentymocketsFactoriesDirPath
    . 'MockOrderRepositoryFactory.php');

use Exception;
use Plenty\Modules\Account\Address\Models\AddressRelationType;
use Plenty\Modules\Account\Contact\Models\ContactType;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentOrderRelation;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\General\AddressDTO;
use Wayfair\Core\Dto\General\BillingInfoDTO;
use Wayfair\Core\Dto\General\WarehouseDTO;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
use Wayfair\Core\Exceptions\CreateOrderException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\BillingAddress;
use Wayfair\Helpers\PaymentHelper;
use Wayfair\Mappers\AddressMapper;
use Wayfair\Mappers\PendingPurchaseOrderMapper;
use Wayfair\Mappers\PurchaseOrderMapper;
use Wayfair\Models\ExternalLogs;
use Wayfair\PlentyMockets\Factories\MockOrderRepositoryFactory;
use Wayfair\PlentyMockets\Helpers\MockPluginApp;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Repositories\PendingOrdersRepository;
use Wayfair\Repositories\WarehouseSupplierRepository;

class CreateOrderServiceTest extends \PHPUnit\Framework\TestCase
{
    const PLENTY_ORDER_ID = 123;
    const ORDER_REFERRER_ID = 2.3;
    const WAREHOUSE_ID = '555';
    const BILLING_ADDRESS_ID = 1;
    const BILLING_CONTACT_ID = 2;
    const DELIVERY_ADDRESS_ID = 3;
    const PAYMENT_ID = 4;

    private $externalLogs;

    /**
     * @before
     */
    public function setUp()
    {
        // set up the pluginApp, which returns empty mocks by default
        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);

        // make a shared ExternalLogs instance so arguments may be included in expectations
        $this->externalLogs = $this->createMock(ExternalLogs::class);
        $mockPluginApp->willReturn(ExternalLogs::class, [], $this->externalLogs);
    }

    /**
     * @after
     */
    public function tearDown()
    {
        // clear out the global pluginApp
        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);
    }

    /**
     * Undocumented function
     *
     * @param string $label the label for the test case
     * @param int|null $expectedResult the expected result of calling create - null if it should not finish
     * @param mixed $poNumber the PO Number in the PO DTO that gets passed into create
     * @param float|null $orderReferrerId the order referrer ID that Wayfair has cached
     * @param array|null $idsOfExistingOrders the array of IDs for orders that already exist for the PO
     * @param boolean $pendingOrderCreationSuccessful does the Pending Order creation fail or pass?
     * @param boolean $dtoHasBilling does the PO DTO have a billing element?
     * @param array $fetchedWayfairBillingInfo the data created/pulled for Wayfair's billing address, etc.
     * @param boolean $dtoHasShipTo does the PO DTO have a shipTo element?
     * @param array|null $createdDeliveryInfo the array created when processing delivery info
     * @param boolean $dtoHasWarehouse does the PO DTO have a Warehouse element?
     * @param string|null $supplierIdInWarehouseInDto the id element of the Warehouse in the PO DTO
     * @param array|null $warehouseIDs the IDs returned from the Warehouse-Supplier search.
     * @param array|null $orderDataReturnedFromMapper the result of order mapping
     * @param boolean $plentyOrderIsCreated is the plenty order created?
     * @param int|null $plentyOrderId the ID of the plenty order that gets created
     * @param boolean $paymentIsCreated is the plenty payment created?
     * @param int|null $idOfCreatedPayment the ID of the payment that gets created
     * @param boolean $paymentOrderRelationIsCreated is the paymentOrderRelation created?
     * @param int|null $idOfCreatedPaymentOrderRelation the ID of the paymentOrderRelation created
     * @param boolean $packingSlipCreationThrowsException does the packing slip functionality work correctly?
     * @return void
     *
     * @dataProvider dataProviderForCreate
     */
    public function testCreate(
        string $label,
        $expectedResult,
        $poNumber = null,
        $orderReferrerId = null,
        $idsOfExistingOrders = null,
        bool $pendingOrderCreationSuccessful = false,
        bool $dtoHasBilling = false,
        $fetchedWayfairBillingInfo = [],
        bool $dtoHasShipTo = false,
        $createdDeliveryInfo = null,
        bool $dtoHasWarehouse = false,
        $supplierIdInWarehouseInDto = null,
        $warehouseIDs = null,
        $orderDataReturnedFromMapper = null,
        bool $plentyOrderIsCreated = false,
        $plentyOrderId = null,
        bool $paymentIsCreated = false,
        $idOfCreatedPayment = null,
        bool $paymentOrderRelationIsCreated = false,
        $idOfCreatedPaymentOrderRelation = null,
        bool $packingSlipCreationThrowsException = false
    ) {
        $orderReferralValueChecksExpected = 0;
        $existingOrderChecksExpected = 0;
        $pendingOrderCreationsExpected = 0;
        $dtoBillingGetCallsExpected = 0;
        $billingInfoLookupsExpected = 0;
        $dtoGetShipToCallsExpected = 0;
        $contactAndAddressCreationsExpected = 0;
        $dtoGetWarehouseCallsExpected = 0;
        $dtoWarehouseGetIdCallsExpected = 0;
        $warehouseIdLookupsExpected = 0;
        $purchaseOrderMappingsExpected = 0;
        $orderCreationsExpected = 0;
        $paymentCreationsExpected = 0;
        $paymentOrderRelationCreationsExpected = 0;
        $packingSlipFetchesExpected = 0;

        if (isset($poNumber) && !empty(trim($poNumber))) {
            $orderReferralValueChecksExpected = 1;

            if (isset($orderReferrerId) && $orderReferrerId >= 1) {
                $existingOrderChecksExpected = 1;

                if (isset($idsOfExistingOrders) && !empty($idsOfExistingOrders)) {
                    $pendingOrderCreationsExpected = 1;
                    $expectedResult = CreateOrderService::RETURN_VALUE_EXISTING_ORDERS;
                } else {
                    $dtoBillingGetCallsExpected = 1;

                    if ($dtoHasBilling) {
                        $billingInfoLookupsExpected = 1;

                        if (isset($fetchedWayfairBillingInfo) && !empty($fetchedWayfairBillingInfo)) {
                            $wayfairBillingAddressId = null;
                            if (key_exists('addressId', $fetchedWayfairBillingInfo)) {
                                $wayfairBillingAddressId = $fetchedWayfairBillingInfo['addressId'];
                            }
                            $wayfairBillingContactId = null;

                            if (key_exists('contactId', $fetchedWayfairBillingInfo)) {
                                $wayfairBillingContactId = $fetchedWayfairBillingInfo['contactId'];
                            }

                            if (isset($wayfairBillingAddressId) && $wayfairBillingAddressId >= 1 && isset($wayfairBillingContactId) && $wayfairBillingContactId >= 1) {

                                $dtoGetShipToCallsExpected = 1;

                                if ($dtoHasShipTo) {
                                    $contactAndAddressCreationsExpected = 1;

                                    if (isset($createdDeliveryInfo) && !empty($createdDeliveryInfo)) {

                                        $deliveryAddressId = $createdDeliveryInfo['addressId'];

                                        if (isset($deliveryAddressId) && $deliveryAddressId >= 1) {
                                            $dtoGetWarehouseCallsExpected = 1;

                                            if ($dtoHasWarehouse) {
                                                $dtoWarehouseGetIdCallsExpected = 1;

                                                if (isset($supplierIdInWarehouseInDto) && !empty(trim($supplierIdInWarehouseInDto))) {
                                                    $warehouseIdLookupsExpected = 1;

                                                    if (isset($warehouseIDs) && !empty($warehouseIDs) && !empty($warehouseIDs[0])) {
                                                        $purchaseOrderMappingsExpected = 1;

                                                        if (isset($orderDataReturnedFromMapper) && !empty($orderDataReturnedFromMapper)) {
                                                            $orderCreationsExpected = 1;

                                                            if ($plentyOrderIsCreated) {
                                                                if (isset($plentyOrderId) && $plentyOrderId >= 1) {
                                                                    $pendingOrderCreationsExpected = 1;

                                                                    if ($pendingOrderCreationSuccessful) {
                                                                        $paymentCreationsExpected = 1;

                                                                        if ($paymentIsCreated) {

                                                                            if (isset($idOfCreatedPayment) && $idOfCreatedPayment >= 1) {

                                                                                $paymentOrderRelationCreationsExpected = 1;

                                                                                if ($paymentOrderRelationIsCreated) {

                                                                                    if (isset($idOfCreatedPaymentOrderRelation) && $idOfCreatedPaymentOrderRelation >= 1) {
                                                                                        $expectedResult = $plentyOrderId;

                                                                                        $packingSlipFetchesExpected = 1;
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        /** @var AddressDTO */
        $shipToInWayfairDto = null;

        /** @var BillingInfoDTO */
        $billingInfoFromDto = null;

        if ($dtoHasBilling) {
            /** @var BillingInfoDTO&\PHPUnit\Framework\MockObject\MockObject */
            $billingInfoFromDto = $this->createMock(BillingInfoDTO::class);
        }

        /** @var ResponseDTO&\PHPUnit\Framework\MockObject\MockObject  */
        $purchaseOrderResponseDTO = $this->createMock(ResponseDTO::class);
        $purchaseOrderResponseDTO->expects($this->once())->method('getPoNumber')->willReturn($poNumber);
        $purchaseOrderResponseDTO->expects($this->exactly($dtoBillingGetCallsExpected))->method('getBillingInfo')->willReturn($billingInfoFromDto);

        if ($dtoHasShipTo) {
            /** @var AddressDTO&\PHPUnit\Framework\MockObject\MockObject */
            $shipToInWayfairDto = $this->createMock(AddressDTO::class);
        }

        $purchaseOrderResponseDTO->expects($this->exactly($dtoGetShipToCallsExpected))->method('getShipTo')->willReturn($shipToInWayfairDto);

        /** @var WarehouseDTO */
        $warehouseInDto = null;

        if ($dtoHasWarehouse) {
            /** @var WarehouseDTO&\PHPUnit\Framework\MockObject\MockObject */
            $warehouseInDto = $this->createMock(WarehouseDTO::class);
            $warehouseInDto->expects($this->exactly($dtoWarehouseGetIdCallsExpected))->method('getId')->willReturn($supplierIdInWarehouseInDto);
        }

        $purchaseOrderResponseDTO->expects($this->exactly($dtoGetWarehouseCallsExpected))->method('getWarehouse')->willReturn($warehouseInDto);

        /** @var AbstractConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
        $configHelper = $this->createMock(AbstractConfigHelper::class);

        $configHelper->expects($this->exactly($orderReferralValueChecksExpected))->method('getOrderReferrerValue')->willReturn($orderReferrerId);

        /** @var PurchaseOrderMapper&\PHPUnit\Framework\MockObject\MockObject */
        $purchaseOrderMapper = $this->createMock(PurchaseOrderMapper::class);

        $purchaseOrderMapper->expects($this->exactly($purchaseOrderMappingsExpected))->method('map')->with(
            $purchaseOrderResponseDTO,
            self::BILLING_ADDRESS_ID,
            self::BILLING_CONTACT_ID,
            self::DELIVERY_ADDRESS_ID,
            self::ORDER_REFERRER_ID,
            self::WAREHOUSE_ID,
            (string) AbstractConfigHelper::PAYMENT_METHOD_INVOICE
        )->willReturn($orderDataReturnedFromMapper);

        // lookups are happening in a different function, so the OrderRepositoryContract does NOT need to support fetching here.
        /** @var OrderRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $orderRepositoryContract = $this->createMock(OrderRepositoryContract::class);

        /** @var Order */
        $plentyOrder = null;

        if ($plentyOrderIsCreated) {
            /** @var Order&\PHPUnit\Framework\MockObject\MockObject */
            $plentyOrder = $this->createMock(Order::class);

            $plentyOrder->id = $plentyOrderId;
        }

        $orderRepositoryContract->expects($this->exactly($orderCreationsExpected))->method('createOrder')->with(
            $orderDataReturnedFromMapper
        )->willReturn($plentyOrder);

        /** @var PendingOrdersRepository&\PHPUnit\Framework\MockObject\MockObject */
        $pendingOrdersRepository = $this->createMock(PendingOrdersRepository::class);

        /** @var AddressMapper&\PHPUnit\Framework\MockObject\MockObject */
        $addressMapper = $this->createMock(AddressMapper::class);

        /** @var KeyValueRepository&\PHPUnit\Framework\MockObject\MockObject */
        $keyValueRepository = $this->createMock(KeyValueRepository::class);

        /** @var WarehouseSupplierRepository&\PHPUnit\Framework\MockObject\MockObject */
        $warehouseSupplierRepository = $this->createMock(WarehouseSupplierRepository::class);

        $warehouseSupplierRepository->expects($this->exactly($warehouseIdLookupsExpected))->method('findWarehouseIds')->with(
            $supplierIdInWarehouseInDto
        )->willReturn($warehouseIDs);

        /** @var PaymentRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $paymentRepositoryContract = $this->createMock(PaymentRepositoryContract::class);

        /** @var PaymentHelper&\PHPUnit\Framework\MockObject\MockObject */
        $paymentHelper = $this->createMock(PaymentHelper::class);

        /** @var Payment */
        $plentyPayment = null;

        if ($paymentIsCreated) {
            /** @var Payment&\PHPUnit\Framework\MockObject\MockObject */
            $plentyPayment = $this->createMock(Payment::class);
            $plentyPayment->id = $idOfCreatedPayment;
        }

        /** @var PaymentOrderRelation */
        $paymentOrderRelation = null;

        if ($paymentOrderRelationIsCreated) {
            /** @var PaymentOrderRelation&\PHPUnit\Framework\MockObject\MockObject */
            $paymentOrderRelation = $this->createMock(PaymentOrderRelation::class);
            $paymentOrderRelation->id = $idOfCreatedPaymentOrderRelation;
        }

        /** @var PaymentOrderRelationRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $paymentOrderRelationRepositoryContract = $this->createMock(PaymentOrderRelationRepositoryContract::class);

        $paymentOrderRelationRepositoryContract->expects($this->exactly($paymentOrderRelationCreationsExpected))->method('createOrderRelation')->with(
            $plentyPayment,
            $plentyOrder
        )->willReturn($paymentOrderRelation);

        /** @var PendingPurchaseOrderMapper&\PHPUnit\Framework\MockObject\MockObject */
        $pendingPurchaseOrderMapper = $this->createMock(PendingPurchaseOrderMapper::class);

        /** @var PendingOrdersRepository&\PHPUnit\Framework\MockObject\MockObject */
        $pendingOrdersRepository = $this->createMock(PendingOrdersRepository::class);

        /** @var SavePackingSlipService&\PHPUnit\Framework\MockObject\MockObject */
        $savePackingSlipService = $this->createMock(SavePackingSlipService::class);

        if ($packingSlipCreationThrowsException) {
            // should NOT halt execution
            $savePackingSlipService->expects($this->exactly($packingSlipFetchesExpected))->method('save')->with($plentyOrderId, $poNumber)->willThrowException(new Exception("Test-time packing slip failure"));
        } else {
            $savePackingSlipService->expects($this->exactly($packingSlipFetchesExpected))->method('save')->with($plentyOrderId, $poNumber);
        }

        /** @var AddressService&\PHPUnit\Framework\MockObject\MockObject */
        $addressService = $this->createMock(AddressService::class);

        $addressService->expects($this->exactly($contactAndAddressCreationsExpected))->method('createContactAndAddress')->with(
            $shipToInWayfairDto,
            $billingInfoFromDto,
            $orderReferrerId,
            ContactType::TYPE_CUSTOMER,
            AddressRelationType::DELIVERY_ADDRESS
        )->willReturn($createdDeliveryInfo);

        /** @var LoggerContract&\PHPUnit\Framework\MockObject\MockObject */
        $logger = $this->createMock(LoggerContract::class);

        /** @var LogSenderService&\PHPUnit\Framework\MockObject\MockObject */
        $logSenderService = $this->createMock(LogSenderService::class);

        /** @var CreateOrderService&\PHPUnit\Framework\MockObject\MockObject */
        $createOrderService = $this->createPartialMock(CreateOrderService::class, [
            'createPayment',
            'createPendingOrder',
            'getIdsOfExistingPlentyOrders',
            'getOrCreateBillingInfoForWayfair',
        ]);
        $createOrderService->__construct(
            $purchaseOrderMapper,
            $addressMapper,
            $orderRepositoryContract,
            $keyValueRepository,
            $warehouseSupplierRepository,
            $paymentRepositoryContract,
            $paymentHelper,
            $paymentOrderRelationRepositoryContract,
            $pendingPurchaseOrderMapper,
            $pendingOrdersRepository,
            $savePackingSlipService,
            $addressService,
            $configHelper,
            $logger,
            $logSenderService
        );
        // mock out the subroutines - these should be tested in their own respective harnesses


        $createOrderService->expects($this->exactly($pendingOrderCreationsExpected))->method('createPendingOrder')->with($purchaseOrderResponseDTO)->willReturn($pendingOrderCreationSuccessful);

        $createOrderService->expects($this->exactly($existingOrderChecksExpected))->method('getIdsOfExistingPlentyOrders')->with($poNumber)->willReturn($idsOfExistingOrders);

        $wfAddressDto = AddressDTO::createFromArray(BillingAddress::BillingAddressAsArray);

        $createOrderService->expects($this->exactly($billingInfoLookupsExpected))->method('getOrCreateBillingInfoForWayfair')->with(
            $wfAddressDto,
            $billingInfoFromDto,
            $orderReferrerId,
            $this->externalLogs
        )->willReturn($fetchedWayfairBillingInfo);

        $createOrderService->expects($this->exactly($paymentCreationsExpected))->method('createPayment')->with($poNumber)->willReturn($plentyPayment);

        if (!isset($expectedResult)) {
            $this->expectException(CreateOrderException::class);
        }

        $actual = $createOrderService->create($purchaseOrderResponseDTO);

        $this->assertEquals($expectedResult, $actual, $label);
    }

    /**
     * Inputs for testCreate.
     *
     * - testCreate determines the expected result based on the inputs.
     * - testCreate has default values for all parameters except for the test case message.
     * - the default params are all null/false
     *
     * @return array
     */
    public function dataProviderForCreate()
    {
        $cases = [];

        $cases[] = ['null poNumber', null, null];
        $cases[] = ['empty poNumber value', null, ''];
        $cases[] = ['whitespace poNumber value', null, '      '];
        $cases[] = ['lack of order referrer', null, 'poFoo', null];
        $cases[] = ['negative order referrer', null, 'poFoo', -self::ORDER_REFERRER_ID, []];
        $cases[] = ['order referrer 0', null, 'poFoo', 0, []];
        $cases[] = ['order referrer 0.0', null, 'poFoo', 0.0, []];
        $cases[] = ['one existing order, existing pending order(s)', CreateOrderService::RETURN_VALUE_EXISTING_ORDERS, 'poFoo', self::ORDER_REFERRER_ID, ['1'], false];
        $cases[] = ['multiple existing orders, existing pending order(s)', CreateOrderService::RETURN_VALUE_EXISTING_ORDERS, 'poFoo', self::ORDER_REFERRER_ID, ['1', '2', '3'], false];
        $cases[] = ['one existing order, no pending orders', CreateOrderService::RETURN_VALUE_EXISTING_ORDERS, 'poFoo', self::ORDER_REFERRER_ID, ['1'], true];
        $cases[] = ['multiple existing orders, no pending orders', CreateOrderService::RETURN_VALUE_EXISTING_ORDERS, 'poFoo', self::ORDER_REFERRER_ID, ['1', '2', '3'], true];
        $cases[] = ['lack of billing info in PO DTO', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, false];
        $cases[] = ['empty billing info for Wayfair', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, []];
        $cases[] = ['billing info for Wayfair missing addressId', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID]];
        $cases[] = ['billing info for Wayfair missing contactId', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['addressId' => self::BILLING_ADDRESS_ID]];
        $cases[] = ['shipto info missing from PO DTO', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], false];
        $cases[] = ['empty delivery info got returned', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, []];
        $cases[] = ['warehouse missing from PO DTO', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], false];
        $cases[] = ['null supplier ID', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, null];
        $cases[] = ['empty supplier ID', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, ''];
        $cases[] = ['whitespace supplier ID', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, '     ', [self::WAREHOUSE_ID]];
        $cases[] = ['no warehouse IDs found - null', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', null];
        $cases[] = ['no warehouse IDs found - empty', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', []];
        $cases[] = ['no data returned from order mapper', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], []];
        // contract of OrderRepositoryContract does not let us return null order
        // $cases[] = ['plenty order repo failed to create', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], false];
        $cases[] = ['plenty order id missing', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, null];
        $cases[] = ['plenty order id negative', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, -2];
        $cases[] = ['plenty order id zero', null, 'poFoo', self::ORDER_REFERRER_ID, [], false, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, 0];
        $cases[] = ['failure to create pending order', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true];
        // contract of PaymentRepositoryContract does not let us return null Payment
        // $cases[] = ['payment not created', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, false];
        $cases[] = ['payment id missing', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, null];
        $cases[] = ['payment id negative', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, -3];
        $cases[] = ['payment id zero', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 0];
        // contract of PaymentOrderRelationRepositoryContract does not let su return null relation
        // $cases[] = ['paymentOrderRelation not created', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 222, false];
        $cases[] = ['paymentOrderRelation id missing', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 222, true, null];
        $cases[] = ['paymentOrderRelation id negative', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 222, true, -4];
        $cases[] = ['paymentOrderRelation id zero', null, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 222, true, 0];
        $cases[] = ['successful creation', self::PLENTY_ORDER_ID, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 222, true, 333];
        $cases[] = ['successful creation, with multiple warehouse results', self::PLENTY_ORDER_ID, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID, '999', '8989'], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 222, true, 333];
        $cases[] = ['successful creation, packing slip allowed to fail', self::PLENTY_ORDER_ID, 'poFoo', self::ORDER_REFERRER_ID, [], true, true, ['contactId' => self::BILLING_CONTACT_ID, 'addressId' => self::BILLING_ADDRESS_ID], true, ['addressId' => self::DELIVERY_ADDRESS_ID], true, 'coolCouches', [self::WAREHOUSE_ID], ['order', 'data'], true, self::PLENTY_ORDER_ID, true, 222, true, 333, true];

        return $cases;
    }

    /**
     * Test harness for getIdsOfExistingPlentyOrders
     *
     * @param string $label the label for the test case
     * @param array $expectedResult the expected array of ids
     * @param string|null $poNumber the po number passed in
     * @param array|null $pagesOfOrders pages of arrays returned from the OrderRepositoryContract
     * @return void
     *
     * @dataProvider dataProviderForGetIdsOfExistingPlentyOrders
     */
    public function testGetIdsOfExistingPlentyOrders(string $label, array $expectedResult, $poNumber, $pagesOfOrders)
    {
        // FIXME: set this correctly
        $searchesExpected = (isset($poNumber) && !empty(trim($poNumber))) ? 1 : 0;

        // nullifying expectedFilters means "we don't expect to call setFilters"
        $expectedFilters = null;

        if ($searchesExpected > 0) {
            $expectedFilters = ['externalOrderId' => $poNumber];
        }

        $orderRepositoryFactory = new MockOrderRepositoryFactory($this);
        $orderRepositoryContract = $orderRepositoryFactory->create($pagesOfOrders, $expectedFilters, $searchesExpected);

        // the method under test only uses the OrderRepositoryContract
        // but we need to mock out the rest of the constructor args in order to call it (due to type restrictions)

        /** @var CreateOrderService&\PHPUnit\Framework\MockObject\MockObject */
        $createOrderService = $this->createTestProxy(CreateOrderService::class, [
            $this->createMock(PurchaseOrderMapper::class),
            $this->createMock(AddressMapper::class),
            $orderRepositoryContract,
            $this->createMock(KeyValueRepository::class),
            $this->createMock(WarehouseSupplierRepository::class),
            $this->createMock(PaymentRepositoryContract::class),
            $this->createMock(PaymentHelper::class),
            $this->createMock(PaymentOrderRelationRepositoryContract::class),
            $this->createMock(PendingPurchaseOrderMapper::class),
            $this->createMock(PendingOrdersRepository::class),
            $this->createMock(SavePackingSlipService::class),
            $this->createMock(AddressService::class),
            $this->createMock(AbstractConfigHelper::class),
            $this->createMock(LoggerContract::class),
            $this->createMock(LogSenderService::class),
        ]);

        $actualResult = $createOrderService->getIdsOfExistingPlentyOrders($poNumber);

        $this->assertEquals($expectedResult, $actualResult, $label);
    }

    /**
     * Cases for testGetIdsOfExistingPlentyOrders
     *
     * @return array
     */
    public function dataProviderForGetIdsOfExistingPlentyOrders()
    {
        $cases = [];

        $cases[] = ['null poNumber means no results', [], null, [[['id' => '123']]]];
        $cases[] = ['empty poNumber means no results', [], '', [[['id' => '123']]]];
        $cases[] = ['whitespace poNumber means no results', [], '   ', [[['id' => '123']]]];
        $cases[] = ['valid poNumber without pages means no results', [], 'SomePO', []];
        $cases[] = ['valid poNumber with one empty page means no results', [], 'SomePO', [[]]];
        $cases[] = ['valid poNumber with one empty Order array means no results', [], 'SomePO', [[[]]]];
        $cases[] = ['valid poNumber with one Order', ['123'], 'SomePO', [[['id' => '123']]]];
        $cases[] = ['valid poNumber with multiple Orders', ['123', '456', '789'], 'SomePO', [[['id' => '123'], ['id' => '456'], ['id' => '789']]]];
        $cases[] = ['valid poNumber with a bad order at the beginning', ['456', '789'], 'SomePO', [[['id' => '456'], ['id' => '789']]]];
        $cases[] = ['valid poNumber with a bad order in the middle', ['123', '789'], 'SomePO', [[['id' => '123'], ['id' => '789']]]];
        $cases[] = ['valid poNumber with a bad order at the end', ['123', '456'], 'SomePO', [[['id' => '123'], ['id' => '456']]]];
        return $cases;
    }

    /**
     * Test harness for getOrCreateBillingInfoForWayfair
     *
     * @param string $label the test case label
     * @param array $expectedResult the expected output of getOrCreateBillingInfoForWayfair
     * @param string|null $encodedBillingContactInRepository the data found in the repo
     * @param array|null $createdBillingInfo the billing info array returned from AddressService
     * @return void
     *
     * @dataProvider dataProviderForGetOrCreateBillingInfoForWayfair
     */
    public function testGetOrCreateBillingInfoForWayfair(string $label, array $expectedResult, $encodedBillingContactInRepository, $createdBillingInfo)
    {
        /** @var KeyValueRepository&\PHPUnit\Framework\MockObject\MockObject  */
        $keyValueRepository = $this->createMock(KeyValueRepository::class);

        $keyValueRepository->expects($this->once())->method('get')->willReturn($encodedBillingContactInRepository);

        $expectedCreations = 0;

        if (!isset($encodedBillingContactInRepository) || empty(trim($encodedBillingContactInRepository))) {
            $expectedCreations = 1;
        } else {
            try {
                $decoded = json_decode($encodedBillingContactInRepository);
                if (!isset($decoded) || empty($decoded)) {
                    $expectedCreations = 1;
                }
            } catch (Exception $e) {
                $expectedCreations = 1;
            }
        }

        $expectedPuts = ($expectedCreations > 0 && isset($createdBillingInfo) && !empty($createdBillingInfo)) ? 1 : 0;

        $keyValueRepository->expects($this->exactly($expectedPuts))->method('put');

        /** @var AddressService&\PHPUnit\Framework\MockObject\MockObject */
        $addressService = $this->createMock(AddressService::class);

        /** @var AddressDTO&\PHPUnit\Framework\MockObject\MockObject */
        $addressDTO = $this->createMock(AddressDTO::class);

        /** @var BillingInfoDTO&\PHPUnit\Framework\MockObject\MockObject */
        $billingInfoFromWayfairPurchaseOrderDto = $this->createMock(BillingInfoDTO::class);

        $addressService->expects($this->exactly($expectedCreations))->method('createContactAndAddress')->with(
            $addressDTO,
            $billingInfoFromWayfairPurchaseOrderDto,
            self::ORDER_REFERRER_ID,
            ContactType::TYPE_PARTNER,
            AddressRelationType::BILLING_ADDRESS
        )->willReturn($createdBillingInfo);

        /** @var CreateOrderService&\PHPUnit\Framework\MockObject\MockObject */
        $createOrderService = $this->createTestProxy(CreateOrderService::class, [
            $this->createMock(PurchaseOrderMapper::class),
            $this->createMock(AddressMapper::class),
            $this->createMock(OrderRepositoryContract::class),
            $keyValueRepository,
            $this->createMock(WarehouseSupplierRepository::class),
            $this->createMock(PaymentRepositoryContract::class),
            $this->createMock(PaymentHelper::class),
            $this->createMock(PaymentOrderRelationRepositoryContract::class),
            $this->createMock(PendingPurchaseOrderMapper::class),
            $this->createMock(PendingOrdersRepository::class),
            $this->createMock(SavePackingSlipService::class),
            $addressService,
            $this->createMock(AbstractConfigHelper::class),
            $this->createMock(LoggerContract::class),
            $this->createMock(LogSenderService::class),
        ]);

        // due to argument type declarations, can't pass nulls under test
        // referrer is checked in 'create' before 'getOrCreateBillingInfoForWayfair' is called
        $actualResult = $createOrderService->getOrCreateBillingInfoForWayfair($addressDTO, $billingInfoFromWayfairPurchaseOrderDto, self::ORDER_REFERRER_ID, $this->externalLogs);

        $this->assertEquals($expectedResult, $actualResult, $label);
    }

    /**
     * Cases for testGetOrCreateBillingInfoForWayfair
     *
     * @return array
     */
    public function dataProviderForGetOrCreateBillingInfoForWayfair()
    {
        $cases = [];

        $cases[] = ['nothing in repo, and AddressService returns nothing', [], null, []];
        $cases[] = ['empty string in repo, and AddressService returns nothing', [], '', []];
        $cases[] = ['whitespace string in repo, and AddressService returns nothing', [], '    ', []];
        $cases[] = ['invalid json in repo, and AddressService returns nothing', [], '{foo', []];
        $cases[] = ['nothing in repo, use AddressService result', ['faz' => 'baz'], null, ['faz' => 'baz']];
        $cases[] = ['empty string in repo, use AddressService result', ['faz' => 'baz'], '', ['faz' => 'baz']];
        $cases[] = ['whitespace string in repo, use AddressService result', ['faz' => 'baz'], '    ', ['faz' => 'baz']];
        $cases[] = ['invalid json in repo, use AddressService result', ['faz' => 'baz'], '{foo', ['faz' => 'baz']];
        $cases[] = ['valid json string in repo, use repo result V1', ['foo' => 'bar'], '{"foo": "bar"}', []];
        $cases[] = ['valid json string in repo, use repo result V2', ['foo' => 'bar'], '{"foo": "bar"}', ['faz' => 'baz']];

        return $cases;
    }

    /**
     * Test harness for createPendingOrder
     *
     * @param string $label the test case's label
     * @param boolean $expectedResult the expected outcome of the creation call
     * @param string|null $poNumber the PO number in the PO Response DTO
     * @param array|null $existingPendingOrder the pending order that already exists for the PO
     * @param array|null $mappingResults the results of mapping to a new pending order
     * @param bool $insertionResults the result of trying to insert the new pending order
     * @return void
     *
     * @dataProvider dataProviderForCreatePendingOrder
     */
    public function testCreatePendingOrder(string $label, bool $expectedResult, $poNumber = null, $existingPendingOrder = null, $mappingResults = null, $insertionResults = false)
    {
        $expectedPendingOrderFetches = 0;
        $expectedPendingOrderMappings = 0;
        $expectedPendingOrderPuts = 0;

        if (isset($poNumber) && !empty(trim($poNumber))) {
            $expectedPendingOrderFetches = 1;

            if (!isset($existingPendingOrder) || empty($existingPendingOrder)) {
                $expectedPendingOrderMappings = 1;

                if (isset($mappingResults) && !empty($mappingResults)) {
                    $expectedPendingOrderPuts = 1;
                }
            }
        }

        /** @var ResponseDTO&\PHPUnit\Framework\MockObject\MockObject */
        $wfPurchaseOrderResponseDTO = $this->createMock(ResponseDTO::class);
        $wfPurchaseOrderResponseDTO->expects($this->once())->method('getPoNumber')->willReturn($poNumber);

        /** @var PendingOrdersRepository&\PHPUnit\Framework\MockObject\MockObject */
        $wfPendingOrdersRepository = $this->createMock(PendingOrdersRepository::class);
        $wfPendingOrdersRepository->expects($this->exactly($expectedPendingOrderFetches))->method('get')->with($poNumber)->willReturn($existingPendingOrder);
        $wfPendingOrdersRepository->expects($this->exactly($expectedPendingOrderPuts))->method('insert')->with($mappingResults)->willReturn($insertionResults);

        /** @var PendingPurchaseOrderMapper&\PHPUnit\Framework\MockObject\MockObject */
        $wfPendingPurchaseOrderMapper = $this->createMock(PendingPurchaseOrderMapper::class);
        $wfPendingPurchaseOrderMapper->expects($this->exactly($expectedPendingOrderMappings))->method('map')->with($wfPurchaseOrderResponseDTO)->willReturn($mappingResults);

        /** @var CreateOrderService&\PHPUnit\Framework\MockObject\MockObject */
        $createOrderService = $this->createTestProxy(CreateOrderService::class, [
            $this->createMock(PurchaseOrderMapper::class),
            $this->createMock(AddressMapper::class),
            $this->createMock(OrderRepositoryContract::class),
            $this->createMock(KeyValueRepository::class),
            $this->createMock(WarehouseSupplierRepository::class),
            $this->createMock(PaymentRepositoryContract::class),
            $this->createMock(PaymentHelper::class),
            $this->createMock(PaymentOrderRelationRepositoryContract::class),
            $wfPendingPurchaseOrderMapper,
            $wfPendingOrdersRepository,
            $this->createMock(SavePackingSlipService::class),
            $this->createMock(AddressService::class),
            $this->createMock(AbstractConfigHelper::class),
            $this->createMock(LoggerContract::class),
            $this->createMock(LogSenderService::class),
        ]);


        $actualResult = $createOrderService->createPendingOrder($wfPurchaseOrderResponseDTO);

        $this->assertEquals($expectedResult, $actualResult, $label);
    }

    /**
     * UCases for testCreatePendingOrder
     *
     * @return array
     */
    public function dataProviderForCreatePendingOrder()
    {
        $cases = [];

        $cases[] = ['null PO number error', false, null];
        $cases[] = ['existing pending order early exit', true, 'myPO', ['existing', 'pending', 'order', 'data']];
        $cases[] = ['mapping failure - empty', false, 'myPO', [], []];
        $cases[] = ['successful mapping but failed insertion', false, 'myPO', [], [], false];
        $cases[] = ['successful mapping and successful insertion', true, 'myPO', [], ['created', 'pending', 'order', 'data'], true];
        return $cases;
    }
}
