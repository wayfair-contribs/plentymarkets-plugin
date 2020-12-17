<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

$plentymocketsFactoriesDirPath = dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Factories' . DIRECTORY_SEPARATOR;

require_once($plentymocketsFactoriesDirPath
    . 'MockVariationSearchRepositoryFactory.php');

require_once($plentymocketsFactoriesDirPath
    . 'VariationDataFactory.php');

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Helpers' . DIRECTORY_SEPARATOR . 'MockPluginApp.php');

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Overrides' . DIRECTORY_SEPARATOR . 'ReplacePluginApp.php');

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR . 'TestTimeLogger.php');

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\Core\Api\Services\InventoryService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Exceptions\InventorySyncInProgressException;
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Core\Exceptions\NoReferencePointForPartialInventorySyncException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Models\InventoryUpdateResult;
use Wayfair\PlentyMockets\Factories\MockVariationSearchRepositoryFactory;
use Wayfair\PlentyMockets\Factories\VariationDataFactory;
use Wayfair\PlentyMockets\Helpers\MockPluginApp;
use Wayfair\Services\InventoryStatusService;
use Wayfair\Services\InventoryUpdateService;
use Wayfair\Test\TestTimeLogger;

/**
 * Tests for InventoryUpdateService
 */
final class InventoryUpdateServiceTest extends \PHPUnit\Framework\TestCase
{
    const METHOD_CREATE = 'create';

    const TIMESTAMP_OVERDUE = '2020-10-05 12:34:02.000000 +02:00';
    const TIMESTAMP_RECENT = '2020-10-06 17:44:02.000000 +02:00';
    const TIMESTAMP_NOW = '2020-10-06 17:45:02.000000 +02:00';
    const TIMESTAMP_FUTURE = '2035-10-06 17:44:02.000000 +02:00';
    const W3C_RECENT = '2020-10-06T15:44:02+00:00';

    const REFERRER_ID = 12345;
    const STOCK_BUFFER = 3;
    const WINDOW_START = 1000;
    const WINDOW_END = 2000;
    const ELAPSED_TIME = 5;

    /** @var ExternalLogs */
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

        // $mockPluginApp->willReturn(InventoryUpdateResult::class, [], new InventoryUpdateResult());
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
     * Test the partial sync window calculations
     *
     * @param mixed $expected
     * @return void
     *
     * @dataProvider dataProviderForTestCalculateStartOfDeltaSyncWindow
     */
    public function testCalculateStartOfDeltaSyncWindow($name, $expected, $lastCompletionStartPartial, $lastCompletionStartFull)
    {

        /** @var InventoryStatusService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryStatusService = $this->createMock(InventoryStatusService::class);
        $inventoryStatusService->method('getLastCompletionStart')->willReturnMap([
            [false, $lastCompletionStartPartial],
            [true, $lastCompletionStartFull],
        ]);

        if (!isset($expected) || empty($expected)) {
            $this->expectException(InventorySyncBlockedException::class);
        }


        $result = InventoryUpdateService::calculateStartOfDeltaSyncWindow($inventoryStatusService);

        $this->assertEquals($expected, $result, $name);
    }

    public function dataProviderForTestCalculateStartOfDeltaSyncWindow()
    {
        $cases[] = ['Lack of data should cause exception', null, '', ''];

        $cases[] = ['lack of full sync should cause exception', null, self::TIMESTAMP_RECENT, ''];

        $cases[] = ['knowing only full sync should return full sync time', self::W3C_RECENT, '', self::TIMESTAMP_RECENT];

        $cases[] = ['partial sync happening more recently than full sync should return partial sync time', self::W3C_RECENT, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE];

        $cases[] = ['full sync happening more recently than partial sync should return full sync time', self::W3C_RECENT, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT];

        $cases[] = ['both kinds of sync happening at the same time should return that duplicated time', self::W3C_RECENT, self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT];

        return $cases;
    }

    /**
     * Test Inventory Sync
     *
     * @return void
     *
     * @dataProvider dataProviderForTestSync
     */
    public function testSync(
        string $name,
        $expectedExceptionClass = null,
        bool $fullInventory = false,
        $statusInDatabase = '',
        $mostRecentAttemptTime = null,
        $windowStart = null,
        $startExpected = false,
        array $syncResultsForPages = [],
        $exceptionThrownByPageSync = null
    ) {
        /** @var MockPluginApp $mockPluginApp*/
        global $mockPluginApp;

        if (!isset($syncResultsForPages) || empty($syncResultsForPages)) {
            $defaultResult = new InventoryUpdateResult($fullInventory);
            // set last page flag to prevent loop
            $defaultResult->setLastPage(true);
            $syncResultsForPages = [$defaultResult];
        }

        /** @var InventoryService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryService = $this->createMock(InventoryService::class);
        /** @var InventoryMapper&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryMapper = $this->createMock(InventoryMapper::class);

        /** @var InventoryStatusService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryStatusService = $this->createMock(InventoryStatusService::class);
        $inventoryStatusService->expects($this->once())->method('getServiceStatusValue')->willReturn($statusInDatabase);


        if ($statusInDatabase == InventoryStatusService::FULL || ($statusInDatabase == InventoryStatusService::PARTIAL)) {
            $inventoryStatusService->expects($this->once())->method('hasGoneOverTimeLimit')->willReturn(!isset($mostRecentAttemptTime) || empty(trim($mostRecentAttemptTime)) || $mostRecentAttemptTime == self::TIMESTAMP_OVERDUE);
        } else {
            $inventoryStatusService->expects($this->never())->method('hasGoneOverTimeLimit');
        }

        if ($startExpected) {
            $inventoryStatusService->expects($this->once())->method('markInventoryStarted')->willReturn(self::TIMESTAMP_NOW);
        } else {
            $inventoryStatusService->expects($this->never())->method('markInventoryStarted');
        }

        $configHelper = $this->createConfigHelper();
        $logger = new TestTimeLogger();
        /** @var LogSenderService&\PHPUnit\Framework\MockObject\MockObject */
        $logSenderService = $this->createMock(LogSenderService::class);

        /** @var InventoryUpdateService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryUpdateService = $this->createPartialMock(InventoryUpdateService::class, ['syncNextPageOfVariations', 'getStartOfDeltaSyncWindow', 'getEndOfDeltaSyncWindow', 'calculateTimeSinceSyncStart']);
        $inventoryUpdateService->__construct($inventoryService, $inventoryMapper, $inventoryStatusService, $configHelper, $logger, $logSenderService);

        if (isset($expectedExceptionClass) && !empty($expectedExceptionClass)) {
            $this->expectException($expectedExceptionClass);
        }

        $expectedTimeCalculations = 0;
        if (isset($expectedExceptionClass) && !empty(trim($expectedExceptionClass))) {
            // TODO: make sure no Exceptions may be thrown before a call to Sync
            $inventoryUpdateService->expects($this->never())->method('syncNextPageOfVariations');
        } else {
            $pageSyncInvocation = $inventoryUpdateService->expects($this->exactly(count($syncResultsForPages)))->method('syncNextPageOfVariations');

            // TODO: find a way to throw an Exception after processing at least one
            if (isset($exceptionThrownByPageSync)) {
                $pageSyncInvocation->willThrowException($exceptionThrownByPageSync);
            } else {
                $pageSyncInvocation->willReturnOnConsecutiveCalls(...$syncResultsForPages);
                $expectedTimeCalculations = 1;
            }
        }

        $expectedWindowStartCalculations = ($expectedExceptionClass == InventorySyncInProgressException::class) || $fullInventory ? 0 : 1;
        $expectedWindowEndCalculations = 0;
        $startWindowCheck = $inventoryUpdateService->expects($this->exactly($expectedWindowStartCalculations))->method('getStartOfDeltaSyncWindow');
        if ($expectedWindowStartCalculations > 0) {
            if (isset($windowStart) && !empty(trim($windowStart))) {
                $startWindowCheck->willReturn(self::TIMESTAMP_RECENT);
                $expectedWindowEndCalculations = 1;
            } else {
                $startWindowCheck->willThrowException(new NoReferencePointForPartialInventorySyncException("purposely thrown by test case"));
            }
        }

        $inventoryUpdateService->expects($this->exactly($expectedWindowEndCalculations))->method('getEndOfDeltaSyncWindow')->willReturn(self::TIMESTAMP_NOW);

        // TODO: make sure this is not causing a single global instance of InventoryUpdateResult
        $mockPluginApp->willReturn(InventoryUpdateResult::class, [], new InventoryUpdateResult());

        $inventoryUpdateService->expects($this->exactly($expectedTimeCalculations))->method('calculateTimeSinceSyncStart')->willReturn(self::ELAPSED_TIME);

        $actualResult = $inventoryUpdateService->sync($fullInventory);

        if (!$startExpected) {
            $this->assertNull($actualResult, "no result expected");
        } else {
            $expectedDtosAttempted = 0;
            $expectedVariationsAttempted = 0;
            $expectedDtosSaved = 0;
            $expectedDtosFailed = 0;
            $expectedDataGatherMs = 0;
            $expectedDataSendMs = 0;
            $expectedToReachLastPage = false;

            /** @var InventoryUpdateResult $syncResult */
            foreach ($syncResultsForPages as $syncResult) {
                $expectedDtosAttempted += $syncResult->getDtosAttempted();
                $expectedVariationsAttempted += $syncResult->getVariationsAttempted();
                $expectedDtosSaved += $syncResult->getDtosSaved();
                $expectedDtosFailed += $syncResult->getDtosFailed();
                $expectedDataGatherMs += $syncResult->getDataGatherMs();
                $expectedDataSendMs += $syncResult->getDataSendMs();
                $expectedToReachLastPage = $expectedToReachLastPage || $syncResult->getLastPage();
            }

            $this->assertEquals($expectedDtosAttempted, $actualResult->getDtosAttempted(), "total DTOs attempted should be sum of results in page syncs");
            $this->assertEquals($expectedVariationsAttempted, $actualResult->getVariationsAttempted(), "total Variations attempted should be sum of results in page syncs");
            $this->assertEquals($expectedDtosSaved, $actualResult->getDtosSaved(), "total DTOs saved should be sum of results in page syncs");
            $this->assertEquals($expectedDtosFailed, $actualResult->getDtosFailed(), "total DTOs saved should be sum of results in page syncs");
            // start/stop times are obtained outside of the loop over pages of Variations.
            // A constant is subbed in, to make sure that the value is making it back out to this resulting object.
            $this->assertEquals(self::ELAPSED_TIME, $actualResult->getElapsedTime(), "total Elapsed Time should be more than zero");
            $this->assertEquals($expectedDataGatherMs, $actualResult->getDataGatherMs(), "total Data Gather Time should be sum of results in page syncs");
            $this->assertEquals($expectedDataSendMs, $actualResult->getDataSendMs(), "total Data Send Time should be sum of results in page syncs");
            $this->assertEquals($fullInventory, $actualResult->getFullInventory(), "request for Full Inventory sync should result in Full Inventory sync");
            $this->assertEquals($expectedToReachLastPage, $actualResult->getLastPage(), "should only reach last page if started and no Exceptions thrown during sync");
        }
    }

    public function dataProviderForTestSync()
    {
        $cases = [];

        $cases[] = ["partial syncs are blocked when no sync of any type is complete v1", NoReferencePointForPartialInventorySyncException::class, false, ''];
        $cases[] = ["partial syncs are blocked when no sync of any type is complete v2", NoReferencePointForPartialInventorySyncException::class, false, InventoryStatusService::STATE_IDLE];
        $cases[] = ["partial syncs are blocked when no sync of any type is complete v3", NoReferencePointForPartialInventorySyncException::class, false, InventoryStatusService::PARTIAL];
        $cases[] = ["partial syncs are blocked when no sync of any type is complete v4", NoReferencePointForPartialInventorySyncException::class, false, InventoryStatusService::FULL];

        $cases[] = ["partial syncs override a partial sync without a start time v1", null, false, InventoryStatusService::PARTIAL, null, self::TIMESTAMP_RECENT, true];

        $cases[] = ["partial syncs override a full sync without a start time", null, false, InventoryStatusService::FULL, null, self::TIMESTAMP_RECENT, true];

        $cases[] = ["partial syncs are blocked when a partial sync is running v1", InventorySyncInProgressException::class, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];

        $cases[] = ["partial syncs are blocked when a full sync is running v1", InventorySyncInProgressException::class, false, InventoryStatusService::FULL, self::TIMESTAMP_RECENT];

        $cases[] = ["partial syncs can start when a partial sync has been running for too long v1", null, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, true];

        $cases[] = ["partial syncs can start when a full sync has been running for too long v1", null, false, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, true];

        $cases[] = ["full syncs override a partial sync without a start time v1", null, true, InventoryStatusService::PARTIAL, null, self::TIMESTAMP_RECENT, true];

        $cases[] = ["full syncs override a full sync without a start time", null, true, InventoryStatusService::FULL, null, self::TIMESTAMP_RECENT, true];

        $cases[] = ["full syncs can start when a partial sync has been running for too long v1", null, true, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, true];

        $cases[] = ["full syncs can start when a full sync has been running for too long v1", null, true, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, true];

        $cases[] = ["full syncs can start when a partial sync started recently", null, true, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT, null, true];

        // TODO: cases where syncNextPageOfVariations returns positive results

        // TODO: cases where syncNextPageOfVariations returns negative results

        // TODO: cases where syncNextPageOfVariations throws Exception (at various pages)

        return $cases;
    }

    /**
     * Test Internal loop logic
     * @return void
     *
     * @dataProvider dataProviderForTestSyncNextPageOfVariations
     */
    public function testSyncNextPageOfVariations(
        string $name,
        InventoryUpdateResult $expectedResult,
        $expectedExceptionClass,
        int $pageNumber,
        bool $fullInventory,
        string $syncStartTimeStamp,
        array $cannedRequestDtosForPage,
        array $cannedResponseDtosForPage,
        array $variationDataArraysForPage,
        $lastCompletionStartPartial,
        $lastCompletionStartFull,
        $statusInDatabase,
        $allItemsActive = false,
        $mostRecentAttemptTime = null
    ) {

        global $mockPluginApp;

        if (isset($expectedExceptionClass) && !empty($expectedExceptionClass)) {
            $this->expectException($expectedExceptionClass);
        }

        /** @var VariationSearchRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $variationSearchRepository = $this->createMock(VariationSearchRepositoryContract::class);

        // FIXME: should not always be expecting a search
        $searchesExpected = 1;

        $numVariations = count($variationDataArraysForPage);

        $expectedSearchParams = [
            'with' => [
                'variationSkus' => true,
                'variationBarcodes' => true,
                'variationMarkets' => true
            ],
            'itemsPerPage' => InventoryUpdateService::VARIATIONS_PER_PAGE,
            'page' => (string)$pageNumber
        ];

        $variationSearchRepository->expects($this->exactly($searchesExpected))->method('setSearchParams')->with(...$expectedSearchParams);
        $variationSearchRepository->expects($this->exactly($searchesExpected))->method('search')->willReturn($variationDataArraysForPage);

        $mockPluginApp->willReturn(VariationSearchRepositoryContract::class, [], $variationSearchRepository);

        /** @var InventoryMapper&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryMapper = $this->createMock(InventoryMapper::class);
        $inventoryMapper->expects($this->exactly($numVariations))->method('createInventoryDTOsFromVariation')->willReturn($cannedRequestDtosForPage);

        $numRequestDtos = count($cannedRequestDtosForPage);
        /** @var InventoryService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryService = $this->createMock(InventoryService::class);
        $inventoryService->expects($this->exactly($numRequestDtos))->method('updateBulk')->willReturn($cannedResponseDtosForPage);

        /** @var InventoryStatusService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryStatusService = $this->createMock(InventoryStatusService::class);
        // FIXME: need to mock the correct methods

        $configHelper = $this->createConfigHelper($allItemsActive);

        $logger = new TestTimeLogger();
        /** @var LogSenderService&\PHPUnit\Framework\MockObject\MockObject */
        $logSenderService = $this->createMock(LogSenderService::class);

        /** @var InventoryUpdateService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryUpdateService = $this->createTestProxy(InventoryUpdateService::class, [
            $inventoryService,
            $inventoryMapper,
            $inventoryStatusService,
            $configHelper,
            $logger,
            $logSenderService
        ]);

        /** @var InventoryUpdateResult */
        $actualResult = $inventoryUpdateService->syncNextPageOfVariations(
            $pageNumber,
            $fullInventory,
            AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
            self::REFERRER_ID,
            self::STOCK_BUFFER,
            $syncStartTimeStamp,
            $variationSearchRepository,
            self::WINDOW_START,
            self::WINDOW_END,
            $this->externalLogs
        );

        if (isset($actualResult) && isset($expectedResult)) {

            // TODO: remove if the low precision of elapsed time (seconds) causes false negatives
            $this->assertGreaterThan(0, $actualResult->getElapsedTime(), "Page sync should have spent some time doing work");

            // TODO: make this conditional if there are Exceptions, etc. that will prevent data gathering
            $this->assertGreaterThan(0, $actualResult->getDataGatherMs(), "Page sync should have spent time gathering data");

            if (count($cannedRequestDtosForPage)) {
                $this->assertGreaterThan(0, $actualResult->getDataSendMs(), "Page sync should have spent some time sending data");
            } else {
                $this->assertEquals(0, $actualResult->getDataSendMs(), "Page sync should NOT have spent any time sending data");
            }

            // HACK: overwrite some expected values with asserted values in order to use object equality for the rest of the assertions
            $expectedResult->setElapsedTime($actualResult->getElapsedTime());
            $expectedResult->setDataGatherMs($actualResult->getDataGatherMs());
            $expectedResult->setDataSendMs($actualResult->getDataSendMs());
        }

        $this->assertEquals($expectedResult, $actualResult, $name);
    }

    public function dataProviderForTestSyncNextPageOfVariations()
    {
        $variationDataFactory = new VariationDataFactory();

        $emptyResultPartial = new InventoryUpdateResult();
        $emptyResultFull = new InventoryUpdateResult();
        $emptyResultFull->setFullInventory(true);

        $collectionOneVariation[] = $variationDataFactory->create(1, [12345]);

        $collectionFiveVariations[] = [];
        for ($i = 0; $i < 5; $i++) {
            $collectionFiveVariations[] = $variationDataFactory->create(1, [12345]);
        }

        $collectionFiveHundredVariations = [];
        for ($i = 0; $i < 500; $i++) {
            $collectionFiveHundredVariations[] = $variationDataFactory->create(1, [12345]);
        }

        $cases = [];

        /*
        $cases[] = ["no variations with all items active should cause a partial sync to clean exit", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE];
        $cases[] = ["no variations should cause a full sync to clean exit", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE];
        $cases[] = ["no variations with all items active should cause a partial sync to clean exit", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE, true];
        $cases[] = ["no variations with all items active should cause a full sync to clean exit", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE, true];

        $cases[] = ["partial sync should stop if any sync starts running while it is running", null, InventorySyncInterruptedException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["full sync should should stop if any sync starts running while it is running", null, InventorySyncInterruptedException::class, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE, false, self::TIMESTAMP_FUTURE];

        $cases[] = ["partial syncs are blocked when no sync of any type is recorded", null, InventorySyncBlockedException::class, false, [], [], [], '', '', InventoryStatusService::STATE_IDLE];

        $cases[] = ["partial syncs are blocked when no full sync has been recorded v1", null, InventorySyncBlockedException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::STATE_IDLE];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v2", null, InventorySyncBlockedException::class, false, [], [], [], '', '', InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE, false];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v3", null, NoReferencePointForPartialInventorySyncException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v4", null, NoReferencePointForPartialInventorySyncException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v5", null, NoReferencePointForPartialInventorySyncException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];

        $cases[] = ["partial syncs are blocked when a partial sync is running v1", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v2", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v3", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v4", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v5", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v6", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v7", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v8", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v9", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v10", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v11", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v12", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v13", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v14", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_FUTURE];

        $cases[] = ["partial syncs are blocked when a full sync is running v1", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v2", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v3", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v4", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v5", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v6", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v7", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v8", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::FULL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v9", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v10", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v11", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v12", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::FULL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v13", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v14", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_FUTURE];

        $cases[] = ["partial syncs can start when a partial sync has been running for too long v1", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a partial sync has been running for too long v2", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a partial sync has been running for too long v3", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a partial sync has been running for too long v4", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];

        $cases[] = ["partial syncs can start when a full sync has been running for too long v1", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a full sync has been running for too long v2", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a full sync has been running for too long v3", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a full sync has been running for too long v4", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];

        $cases[] = ["full syncs can start when a partial sync has been running for too long v1", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a partial sync has been running for too long v2", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a partial sync has been running for too long v3", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a partial sync has been running for too long v4", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_OVERDUE];

        $cases[] = ["full syncs can start when a full sync has been running for too long v1", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a full sync has been running for too long v2", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a full sync has been running for too long v3", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a full sync has been running for too long v4", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, false, self::TIMESTAMP_OVERDUE];

        $cases[] = ["full syncs can start when a partial sync is running v1", $emptyResultFull, null, true, [], [], [], '', '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v2", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v3", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v4", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v5", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v6", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v7", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];

        $cases[] = ["full sync with no request DTOs", $emptyResultFull, null, true, [[]], [], [$collectionOneVariation], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, false, self::TIMESTAMP_RECENT];
        */

        // TODO: make sure sync method returns all DTOs that InventoryService returns to it

        // TODO: make sure sync method returns correct summation of failures

        // TODO: make sure sync method returns correct summation of successfully sent DTOs

        // TODO: No ResponseDTOs from InventoryService->updateBulk

        return $cases;
    }

    private function createInventoryMapper(array $cannedRequestDtoCollections, int $numVariations): InventoryMapper
    {
        /** @var InventoryMapper&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryMapper = $this->createPartialMock(InventoryMapper::class, ['createInventoryDTOsFromVariation']);

        $inventoryMapper->expects($this->exactly($numVariations))->method('createInventoryDTOsFromVariation')->willReturnOnConsecutiveCalls(...$cannedRequestDtoCollections);

        return $inventoryMapper;
    }

    private function createInventoryService(array $cannedResponseDtos): InventoryService
    {
        /** @var InventoryService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryService = $this->createMock(InventoryService::class);
        // one responseDTO per page
        $inventoryService->method('updateBulk')->willReturnOnConsecutiveCalls(...$cannedResponseDtos);

        return $inventoryService;
    }

    private function createConfigHelper($allItemsActive = false): AbstractConfigHelper
    {
        /** @var AbstractConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
        $configHelper = $this->createMock(AbstractConfigHelper::class);
        $configHelper->method('getOrderReferrerValue')->willReturn(self::REFERRER_ID);
        $configHelper->method('isAllItemsActive')->willReturn($allItemsActive);

        return $configHelper;
    }
}
