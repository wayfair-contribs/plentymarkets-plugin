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
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
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
     * Test harness for create method
     *
     * @param string $msg
     * @param integer $expected
     * @param ResponseDTO $purchaseOrderResponseDTO
     * @param array $pagesOfExistingOrders
     * @return void
     *
     * @dataProvider dataProviderForCreate
     */
    public function testCreate(string $msg, int $expected, ResponseDTO $purchaseOrderResponseDTO, array $pagesOfExistingOrders, array $warehouseIDs, array $orderData, Order $plentyOrder, bool $pendingOrderCreationSuccessful)
    {
        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);

        $orderRepositoryFactory = new MockOrderRepositoryFactory($this);

        $orderRepositorySearchesExpected = 0;

        $poNumber = '';

        if (isset($purchaseOrderResponseDTO)) {
            $poNumber = $purchaseOrderResponseDTO->getPoNumber();

            if (isset($poNumber) && !empty($poNumber)) {
                $orderRepositorySearchesExpected = 1;
            }
        }

        $poMappingsExpected = 0;

        if (isset($warehouseIDs) && in_array(self::WAREHOUSE_ID, $warehouseIDs)) {
            $poMappingsExpected = 1;
        } else {
            $this->expectException(Exception::class);
        }

        /** @var PurchaseOrderMapper&\PHPUnit\Framework\MockObject\MockObject */
        $purchaseOrderMapper = $this->createMock(PurchaseOrderMapper::class);

        $purchaseOrderMapper->expects($this->exactly($poMappingsExpected))->method('map')->with(
            $purchaseOrderResponseDTO,
            self::BILLING_ADDRESS_ID,
            self::BILLING_CONTACT_ID,
            self::ORDER_REFERRER_ID,
            self::WAREHOUSE_ID,
            (string) AbstractConfigHelper::PAYMENT_METHOD_INVOICE
        )->willReturn($orderData);

        $orderRepositoryCreationsExpected = 0;

        if (isset($orderData) && !empty($orderData)) {
            $orderRepositoryCreationsExpected = 1;
        } else {
            $this->expectException(Exception::class);
        }

        /** @var OrderRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $orderRepositoryContract = $orderRepositoryFactory->create($pagesOfExistingOrders, ['externalOrderId' => $poNumber], $orderRepositorySearchesExpected);
        $orderRepositoryContract->expects($this->exactly($orderRepositoryCreationsExpected))->method('createOrder')->with($orderData)->willReturn($plentyOrder);

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

        /** @var AbstractConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
        $configHelper = $this->createMock(AbstractConfigHelper::class);

        $configHelper->expects($this->once())->method('getOrderReferrerValue')->willReturn(self::ORDER_REFERRER_ID);

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

        /** @var Payment&\PHPUnit\Framework\MockObject\MockObject */
        $payment = $this->createMock(Payment::class);
        $payment->id = self::PAYMENT_ID;

        $createOrderService->method('createPayment')->willReturn($payment);

        $pendingOrderCreationsExpected = 0;

        if (isset($order) && isset($order->id) && $order->id > 0) {
            $pendingOrderCreationsExpected = 1;
        } else {
            $this->expectException(Exception::class);
        }

        $createOrderService->expects($this->exactly($pendingOrderCreationsExpected))->method('createPendingOrder')->willReturn($pendingOrderCreationSuccessful);

        $actual = $createOrderService->create($purchaseOrderResponseDTO);

        $this->assertEquals($expected, $actual, $msg);
    }

    public function dataProviderForCreate()
    {
        $cases = [];

        // TODO: fill out cases

        return $cases;
    }
}
