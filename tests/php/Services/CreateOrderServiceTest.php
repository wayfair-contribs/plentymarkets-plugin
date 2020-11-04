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

use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Wayfair\Core\Dto\PurchaseOrder\ResponseDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\PlentyMockets\Helpers\MockPluginApp;
use Wayfair\PlentyMockets\Factories\MockOrderRepositoryFactory;

class CreateOrderServiceTest extends \PHPUnit\Framework\TestCase
{

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
    public function testCreate(string $msg, int $expected, ResponseDTO $purchaseOrderResponseDTO, array $pagesOfExistingOrders)
    {
        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);

        /**
         * @var AbstractConfigHelper&\PHPUnit\Framework\MockObject\MockObject
         */
        $configHelper = $this->createMock(AbstractConfigHelper::class);

        $mockPluginApp->willReturn(AbstractConfigHelper::class, [], $configHelper);

        /** @var CreateOrderService&\PHPUnit\Framework\MockObject\MockObject */
        $createOrderService = $this->createPartialMock(CreateOrderService::class, []);

        $orderRepositoryFactory = new MockOrderRepositoryFactory($this);

        $searchesExpected = 0;

        $poNumber = '';

        if (isset($purchaseOrderResponseDTO))
        {
            $poNumber = $purchaseOrderResponseDTO->getPoNumber();

            if (isset($poNumber) && !empty($poNumber))
            {
                $searchesExpected = 1;
            }
        }


        /** @var OrderRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $orderRepositoryContract = $orderRepositoryFactory->create($pagesOfExistingOrders, ['externalOrderId' => $poNumber], $searchesExpected);

        $createOrderService->orderRepositoryContract = $orderRepositoryContract;

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
