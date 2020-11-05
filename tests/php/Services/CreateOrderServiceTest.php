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
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Contracts\PaymentOrderRelationRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentOrderRelation;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
use Wayfair\Core\Exceptions\CreateOrderException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\PaymentHelper;
use Wayfair\Mappers\AddressMapper;
use Wayfair\Mappers\PendingPurchaseOrderMapper;
use Wayfair\Mappers\PurchaseOrderMapper;
use Wayfair\PlentyMockets\Helpers\MockPluginApp;
use Wayfair\PlentyMockets\Factories\MockOrderRepositoryFactory;
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


    /**
     * @before
     */
    public function setUp()
    {
        // set up the pluginApp, which returns empty mocks by default
        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);
    }

    /**
     * Test harness for create method
     *
     * FIXME: param hints in this docblock
     *
     * @param string $msg
     * @param integer $expected
     * @param ResponseDTO $purchaseOrderResponseDTO
     * @param array $existingOrderIDs
     * @return void
     *
     * @dataProvider dataProviderForCreate
     */
    public function testCreate(
        string $msg,
        ResponseDTO $purchaseOrderResponseDTO,
        float $orderReferrerId,
        array $idsOfExistingOrders,
        array $warehouseIDs,
        array $orderDataReturnedFromMapper,
        Order $plentyOrder,
        bool $pendingOrderCreationSuccessful,
        array $wayfairBillingInfo,
        array $deliveryInfoCreated,
        Payment $createdPayment,
        PaymentOrderRelation $createdPaymentOrderRelation
    ) {
        $expectedResult = null;

        $poNumber = null;
        $plentyWarehouseIdFromRepo = null;

        $dtoPoNumberGetsExpected = 0;
        $orderReferralValueChecksExpected = 0;
        $existingOrderChecksExpected = 0;
        $pendingOrderCreationsExpected = 0;
        $orderRepositoryCreationsExpected = 0;
        $dtoBillingGetsExpected = 0;
        $billingInfoLookupsExpected = 0;
        $dtoShipToGetsExpected = 0;
        $contactAndAddressCreationsExpected = 0;
        $dtoWarehouseGetsExpected = 0;
        $dtoWarehouseIdGetsExpected = 0;
        $warehouseIdLookupsExpected = 0;
        $purchaseOrderMappingsExpected = 0;
        $orderCreationsExpected = 0;
        $paymentCreationsExpected = 0;
        $paymentOrderRelationCreationsExpected = 0;
        $packingSlipFetchesExpected = 1;

        if (isset($purchaseOrderResponseDTO)) {
            $dtoPoNumberGetsExpected = 1;
            $poNumber = $purchaseOrderResponseDTO->getPoNumber();

            if (isset($poNumber) && !empty($poNumber)) {
                $orderReferralValueChecksExpected = 1;

                if (isset($orderReferrerId) && $orderReferrerId >= 1) {
                    $existingOrderChecksExpected = 1;

                    if (isset($idsOfExistingOrders) && !empty($idsOfExistingOrders)) {
                        $pendingOrderCreationsExpected = 1;
                        $expectedResult = CreateOrderService::RETURN_VALUE_EXISTING_ORDERS;
                    } else {
                        $dtoBillingGetsExpected = 1;
                        $billingInfoFromDTO = $purchaseOrderResponseDTO->getBillingInfo();

                        if (isset($billingInfoFromDTO) && !empty($billingInfoFromDTO)) {
                            $billingInfoLookupsExpected = 1;

                            if (isset($wayfairBillingInfo) && !empty($wayfairBillingInfo)) {
                                $wayfairBillingAddressId = $wayfairBillingInfo['addressId'];
                                $wayfairBillingContactId = $billingInformationForWayfair['contactId'];

                                if (isset($wayfairBillingAddressId) && $wayfairBillingAddressId >= 1 && isset($wayfairBillingContactId) && $wayfairBillingContactId >= 1) {

                                    $dtoShipToGetsExpected = 1;

                                    if (null !== $purchaseOrderResponseDTO->getShipTo()) {
                                        $contactAndAddressCreationsExpected = 1;

                                        if (isset($deliveryInfoCreated) && !empty($deliveryInfoCreated)) {

                                            $deliveryAddressId = $deliveryInfoCreated['addressId'];

                                            if (isset($deliveryAddressId) && $deliveryAddressId >= 1) {

                                                $dtoWarehouseGetsExpected = 1;

                                                $warehouseInDto = $purchaseOrderResponseDTO->getWarehouse();

                                                if (isset($warehouse) && null !== $warehouse->getId() && !empty($warehouse->getId())) {
                                                    $dtoWarehouseIdGetsExpected = 1;

                                                    $supplierIdInDto = $warehouseInDto->getId();

                                                    if (isset($supplierIdInDto) && !empty($supplierIdInDto)) {
                                                        $warehouseIdLookupsExpected = 1;

                                                        if (isset($warehouseIDs) && !empty($warehouseIDs) && !empty($warehouseIDs[0])) {
                                                            $plentyWarehouseIdFromRepo = $warehouseIDs[0];
                                                            $purchaseOrderMappingsExpected = 1;

                                                            if (isset($orderDataReturnedFromMapper) && !empty($orderDataReturnedFromMapper)) {
                                                                $orderCreationsExpected = 1;

                                                                if (isset($plentyOrder) && isset($plentyOrder->id) && $plentyOrder->id >= 1) {
                                                                    $pendingOrderCreationsExpected = 1;

                                                                    if ($pendingOrderCreationSuccessful)
                                                                    {
                                                                        $paymentCreationsExpected = 1;

                                                                        if (isset($createdPayment) && isset($createdPayment->id) && $createdPayment->id >= 1)
                                                                        {
                                                                            $paymentOrderRelationCreationsExpected = 1;

                                                                            if (isset($createdPaymentOrderRelation) && isset($createdPaymentOrderRelation->id) && $createdPaymentOrderRelation->id >= 1)
                                                                            {
                                                                                $expectedResult = $plentyOrder->id;

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

        $orderRepositoryContract->expects($this->exactly($orderRepositoryCreationsExpected))->method('createOrder')->with($orderDataReturnedFromMapper)->willReturn($plentyOrder);

        /** @var PendingOrdersRepository&\PHPUnit\Framework\MockObject\MockObject */
        $pendingOrdersRepository = $this->createMock(PendingOrdersRepository::class);

        /** @var AddressMapper&\PHPUnit\Framework\MockObject\MockObject */
        $addressMapper = $this->createMock(AddressMapper::class);

        /** @var KeyValueRepository&\PHPUnit\Framework\MockObject\MockObject */
        $keyValueRepository = $this->createMock(KeyValueRepository::class);

        /** @var WarehouseSupplierRepository&\PHPUnit\Framework\MockObject\MockObject */
        $warehouseSupplierRepository = $this->createMock(WarehouseSupplierRepository::class);

        /** @var PaymentRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $paymentRepositoryContract = $this->createMock(PaymentRepositoryContract::class);

        /** @var PaymentHelper&\PHPUnit\Framework\MockObject\MockObject */
        $paymentHelper = $this->createMock(PaymentHelper::class);

        /** @var PaymentOrderRelationRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $paymentOrderRelationRepositoryContract = $this->createMock(PaymentOrderRelationRepositoryContract::class);

        /** @var PendingPurchaseOrderMapper&\PHPUnit\Framework\MockObject\MockObject */
        $pendingPurchaseOrderMapper = $this->createMock(PendingPurchaseOrderMapper::class);

        /** @var PendingOrdersRepository&\PHPUnit\Framework\MockObject\MockObject */
        $pendingOrdersRepository = $this->createMock(PendingOrdersRepository::class);

        /** @var SavePackingSlipService&\PHPUnit\Framework\MockObject\MockObject */
        $savePackingSlipService = $this->createMock(SavePackingSlipService::class);

        /** @var AddressService&\PHPUnit\Framework\MockObject\MockObject */
        $addressService = $this->createMock(AddressService::class);

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

        $createOrderService->expects($this->exactly($pendingOrderCreationsExpected))->method('createPendingOrder')->willReturn($pendingOrderCreationSuccessful);

        $createOrderService->expects($this->exactly($existingOrderChecksExpected))->method('getIdsOfExistingOrders')->with($poNumber)->willReturn($idsOfExistingOrders);

        if (!isset($expectedResult)) {
            $this->expectException(CreateOrderException::class);
        }

        $actual = $createOrderService->create($purchaseOrderResponseDTO);

        $this->assertEquals($expectedResult, $actual, $msg);
    }

    public function dataProviderForCreate()
    {
        $cases = [];

        // TODO: fill out cases

        return $cases;
    }
}
