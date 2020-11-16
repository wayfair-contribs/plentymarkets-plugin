<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Helpers' . DIRECTORY_SEPARATOR . 'MockPluginApp.php');

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Overrides' . DIRECTORY_SEPARATOR . 'ReplacePluginApp.php');

use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\Plugin\DataBase\Contracts\Query;
use Plenty\Modules\StockManagement\Warehouse\Contracts\WarehouseRepositoryContract;
use Plenty\Modules\StockManagement\Warehouse\Models\Warehouse;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Models\WarehouseSupplier;
use Wayfair\PlentyMockets\Helpers\MockPluginApp;

class WarehouseSupplierRepositoryTest extends \PHPUnit\Framework\TestCase
{
    const WAREHOUSE_ID_ALPHA = '11';
    const WAREHOUSE_ID_BETA = '12';
    const SUPPLIER_ID_ALPHA = '21';

    /**
     * Test harness for warehouseExists
     *
     * @param string $msg
     * @param boolean $expected
     * @param mixed $warehouseReturned
     * @return void
     *
     * @dataProvider dataProviderForWarehouseExists
     */
    public function testWarehouseExists(string $msg, bool $expected, $warehouseReturned)
    {
        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);

        $warehouseRepository = $this->createMock(WarehouseRepositoryContract::class);
        $warehouseRepository->method('findById')->willReturn($warehouseReturned);

        $mockPluginApp->willReturn(WarehouseRepositoryContract::class, [], $warehouseRepository);

        // partial mock with empty methods argument proxies to real method but avoids constructor
        // whereas textProxy will call real constructor
        /** @var WarehouseSupplierRepository&\PHPUnit\Framework\MockObject\MockObject */
        $warehouseSupplierRepository = $this->createPartialMock(WarehouseSupplierRepository::class, []);

        $actual = $warehouseSupplierRepository->warehouseExists(123);
        $this->assertEquals($expected, $actual, $msg);
    }

    /**
     * Test harness for findWarehouseIds
     *
     * the entry warehouseExistResults[i] is the result for calling warehouseExists on the ID in queryResult[i]
     *
     * @param string $msg
     * @param array $expected
     * @param string $supplierIDSupplied
     * @param array $queryResult
     * @param array $warehouseExistsResults
     * @return void
     *
     * @dataProvider dataProviderForFindWarehouseIds
     */
    public function testFindWarehouseIds(string $msg, array $expected, string $supplierIDSupplied, array $queryResult, array $warehouseExistsResults)
    {
        $expectedQueryAmount = (isset($supplierIDSupplied) && !empty($supplierIDSupplied)) ? 1 : 0;

        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);

        /** @var Query&\PHPUnit\Framework\MockObject\MockObject */
        $whereQuery = $this->createMock(Query::class);
        $whereQuery->expects($this->exactly($expectedQueryAmount))->method('get')->willReturn($queryResult);

        /** @var Query&\PHPUnit\Framework\MockObject\MockObject */
        $mainQuery = $this->createMock(Query::class);
        $mainQuery->expects($this->exactly($expectedQueryAmount))->method(
            'where'
        )->with(
            'supplierId',
            '=',
            $supplierIDSupplied
        )->willReturn(
            $whereQuery
        );

        /** @var DataBase&\PHPUnit\Framework\MockObject\MockObject */
        $dataBase = $this->createMock(DataBase::class);

        $dataBase->expects($this->exactly($expectedQueryAmount))->method('query')->willReturn($mainQuery);

        $mockPluginApp->willReturn(DataBase::class, [], $dataBase);

        /** @var LoggerContract&\PHPUnit\Framework\MockObject\MockObject */
        $logger = $this->createMock(LoggerContract::class);

        /** @var WarehouseSupplierRepository&\PHPUnit\Framework\MockObject\MockObject */
        $warehouseSupplierRepository = $this->createPartialMock(WarehouseSupplierRepository::class, ['getLogger', 'warehouseExists']);
        $warehouseSupplierRepository->method('getLogger')->willReturn($logger);

        $warehouseSupplierRepository->expects($this->exactly(sizeof($warehouseExistsResults)))->method('warehouseExists')->willReturnOnConsecutiveCalls(...$warehouseExistsResults);

        $actual = $warehouseSupplierRepository->findWarehouseIds($supplierIDSupplied);

        $this->assertEquals($expected, $actual, $msg);
    }

    public function dataProviderForWarehouseExists()
    {
        $cases = [];

        /** @var Warehouse&\PHPUnit\Framework\MockObject\MockObject */
        $emptyWarehouse = $this->createMock(Warehouse::class);

        /** @var Warehouse&\PHPUnit\Framework\MockObject\MockObject */
        $onlyName = $this->createMock(Warehouse::class);
        $onlyName->name = 'foo';

        /** @var Warehouse&\PHPUnit\Framework\MockObject\MockObject */
        $onlyId = $this->createMock(Warehouse::class);
        $onlyId->id = 123;

        /** @var Warehouse&\PHPUnit\Framework\MockObject\MockObject */
        $validWarehouse = $this->createMock(Warehouse::class);
        $validWarehouse->id = 123;
        $validWarehouse->name = 'foo';

        // can't have 'findById' in WarehouseRepositoryContract return null due to Plenty's type hinting
        // $cases[] = ['null warehouse means it does not exist', false, null];

        $cases[] = ['empty warehouse object means it does not exist', false, $emptyWarehouse];
        $cases[] = ['warehouse missing name means it does not exist', false, $onlyId];
        $cases[] = ['warehouse missing id means it does not exist', false, $onlyName];
        $cases[] = ['warehouse with name and ID exists', true, $validWarehouse];

        return $cases;
    }

    public function dataProviderForFindWarehouseIds()
    {
        /** @var WarehouseSupplier&\PHPUnit\Framework\MockObject\MockObject */
        $validMappingAlpha = $this->createMock(WarehouseSupplier::class);
        $validMappingAlpha->warehouseId = self::WAREHOUSE_ID_ALPHA;
        $validMappingAlpha->supplierId = self::SUPPLIER_ID_ALPHA;

        /** @var WarehouseSupplier&\PHPUnit\Framework\MockObject\MockObject */
        $validMappingBeta = $this->createMock(WarehouseSupplier::class);
        $validMappingBeta->warehouseId = self::WAREHOUSE_ID_BETA;
        $validMappingBeta->supplierId = self::SUPPLIER_ID_ALPHA;

        /** @var WarehouseSupplier&\PHPUnit\Framework\MockObject\MockObject */
        $emptyMapping = $this->createMock(WarehouseSupplier::class);

        $singleMappingResult = [$validMappingAlpha];
        $twoMappingResults = [$validMappingAlpha, $validMappingBeta];

        $cases = [];

        $cases[] = ['request with empty string results in no Ids V1', [], '', [], []];
        $cases[] = ['request with empty string results in no Ids V2', [], '', $singleMappingResult, []];
        $cases[] = ['request with empty string results in no Ids V3', [], '', $twoMappingResults, []];

        $cases[] = ['empty result from WarehouseSupplier query results in no Ids', [], '123', [], []];

        $cases[] = ['one nonexisting warehouse results in no Ids', [], self::SUPPLIER_ID_ALPHA, $singleMappingResult, [false]];
        $cases[] = ['one existing warehouse', [self::WAREHOUSE_ID_ALPHA], self::SUPPLIER_ID_ALPHA, $singleMappingResult, [true]];

        $cases[] = ['multiple nonexisting warehouses results in no Ids', [], self::SUPPLIER_ID_ALPHA, $twoMappingResults, [false, false]];
        $cases[] = ['a nonexisting warehouse followed by an existing warehouse', [self::WAREHOUSE_ID_BETA], self::SUPPLIER_ID_ALPHA, $twoMappingResults, [false, true]];
        $cases[] = ['an existing warehouse followed by a nonexisting warehouse', [self::WAREHOUSE_ID_ALPHA], self::SUPPLIER_ID_ALPHA, $twoMappingResults, [true, false]];
        $cases[] = ['two existing warehouses', [self::WAREHOUSE_ID_ALPHA, self::WAREHOUSE_ID_BETA], self::SUPPLIER_ID_ALPHA, $twoMappingResults, [true, true]];

        $cases[] = ['invalid mapping results in no Ids V1', [], self::SUPPLIER_ID_ALPHA, [$emptyMapping], []];
        $cases[] = ['invalid mapping results in no Ids V2', [], self::SUPPLIER_ID_ALPHA, [$emptyMapping], []];

        return $cases;
    }
}
