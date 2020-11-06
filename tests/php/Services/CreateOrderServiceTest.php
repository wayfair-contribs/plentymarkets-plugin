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
use Wayfair\PlentyMockets\Helpers\MockPluginApp;
use Wayfair\Repositories\KeyValueRepository;
use Wayfair\Repositories\PendingOrdersRepository;
use Wayfair\Repositories\WarehouseSupplierRepository;

class CreateOrderServiceTest extends \PHPUnit\Framework\TestCase
{
    const ORDER_REFERRER_ID = 2.3;
    const WAREHOUSE_ID = '123';
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
     * Test harness for create method
     *
     * TODO: param hints in this docblock
     *
     *
     * @dataProvider dataProviderForCreate
     */
    public function testCreate(
        string $msg,
        $poNumber = null,
        $billingInfoFromDto = null,
        bool $dtoIsMissingShipTo = false,
        bool $dtoIsMissingWarehouse = false,
        $supplierIdInWarehouseInDto = null,
        float $orderReferrerId = null,
        $idsOfExistingOrders = null,
        $warehouseIDs = null,
        $orderDataReturnedFromMapper = null,
        bool $plentyOrderIsCreated = false,
        $plentyOrderId = null,
        $pendingOrderCreationSuccessful = null,
        $fetchedWayfairBillingInfo = null,
        $createdDeliveryInfo = null,
        bool $paymentIsCreated = false,
        $idOfCreatedPayment = null,
        bool $paymentOrderRelationIsCreated = false,
        $idOfCreatedPaymentOrderRelation = null,
        bool $packingSlipCreationThrowsException = false
    ) {

        $expectedResult = null;

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

                    if (isset($billingInfoFromDto) && !empty($billingInfoFromDto)) {
                        $billingInfoLookupsExpected = 1;

                        if (isset($fetchedWayfairBillingInfo) && !empty($fetchedWayfairBillingInfo)) {
                            $wayfairBillingAddressId = $fetchedWayfairBillingInfo['addressId'];
                            $wayfairBillingContactId = $fetchedWayfairBillingInfo['contactId'];

                            if (isset($wayfairBillingAddressId) && $wayfairBillingAddressId >= 1 && isset($wayfairBillingContactId) && $wayfairBillingContactId >= 1) {

                                $dtoGetShipToCallsExpected = 1;

                                if (!$dtoIsMissingShipTo) {
                                    $contactAndAddressCreationsExpected = 1;

                                    if (isset($createdDeliveryInfo) && !empty($createdDeliveryInfo)) {

                                        $deliveryAddressId = $createdDeliveryInfo['addressId'];

                                        if (isset($deliveryAddressId) && $deliveryAddressId >= 1) {
                                            $dtoGetWarehouseCallsExpected = 1;

                                            if (!$dtoIsMissingWarehouse) {
                                                $dtoWarehouseGetIdCallsExpected = 1;

                                                if (isset($supplierIdInWarehouseInDto) && !empty($supplierIdInWarehouseInDto)) {
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

        /** @var ResponseDTO&\PHPUnit\Framework\MockObject\MockObject  */
        $purchaseOrderResponseDTO = $this->createMock(ResponseDTO::class);
        $purchaseOrderResponseDTO->expects($this->once())->method('getPoNumber')->willReturn($poNumber);
        $purchaseOrderResponseDTO->expects($this->exactly($dtoBillingGetCallsExpected))->method('getBillingInfo')->willReturn($billingInfoFromDto);

        if (!$dtoIsMissingShipTo) {
            /** @var AddressDTO&\PHPUnit\Framework\MockObject\MockObject */
            $shipToInWayfairDto = $this->createMock(AddressDTO::class);
        }

        $purchaseOrderResponseDTO->expects($this->exactly($dtoGetShipToCallsExpected))->method('getShipTo')->willReturn($shipToInWayfairDto);

        /** @var WarehouseDTO */
        $warehouseInDto = null;

        if (!$dtoIsMissingWarehouse) {
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
        $createOrderService = $this->createTestProxy(CreateOrderService::class, [
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
        ]);

        // mock out the subroutines - these should be tested in their own respective harnesses

        /** @var Payment&\PHPUnit\Framework\MockObject\MockObject */
        $payment = $this->createMock(Payment::class);
        $payment->id = self::PAYMENT_ID;

        $createOrderService->method('createPayment')->willReturn($payment);

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

        $this->assertEquals($expectedResult, $actual, $msg);
    }

    public function dataProviderForCreate()
    {
        $cases = [];

        $cases[] = ['null poNumber should cause exception and no other calculations'];
        $cases[] = ['empty poNumber value should cause exception and no other calculations', ''];
        $cases[] = ['whitespace poNumber value should cause exception and no other calculations', '      '];
        $cases[] = ['lack of order referrer should cause exception and no further calculations', 'poFoo'];
        // TODO: fill out cases

        return $cases;
    }
}
