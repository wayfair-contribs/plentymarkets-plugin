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

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR  . 'TestTimeException.php');

use InvalidArgumentException;
use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\Core\Api\Services\InventoryService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Dto\Inventory\ResponseDTO;
use Wayfair\Core\Exceptions\InventoryException;
use Wayfair\Core\Exceptions\InventorySyncInProgressException;
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Core\Exceptions\NoReferencePointForPartialInventorySyncException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Models\InventoryUpdateResult;
use Wayfair\PlentyMockets\Factories\MockPaginatedResultFactory;
use Wayfair\PlentyMockets\Factories\VariationDataFactory;
use Wayfair\PlentyMockets\Helpers\MockPluginApp;
use Wayfair\Services\InventoryStatusService;
use Wayfair\Services\InventoryUpdateService;
use Wayfair\Test\TestTimeException;
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

        $mockPluginApp->willReturn(InventoryUpdateResult::class, [], new InventoryUpdateResult());
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
     * @dataProvider dataProviderForTestMainSync
     */
    public function testMainSync(
        string $name,
        $startExpected = false,
        $completionExpected = false,
        $expectedExceptionClass = null,
        bool $fullInventory = false,
        $statusInDatabase = '',
        $mostRecentAttemptTime = null,
        $windowStart = null,
        array $syncResultsForPages = [],
        $exceptionThrownByPageSync = null,
        $allItemsActive = false
    ) {
        /** @var MockPluginApp $mockPluginApp*/
        global $mockPluginApp;

        if (!isset($syncResultsForPages) || empty($syncResultsForPages)) {
            $defaultResult = new InventoryUpdateResult($fullInventory, 1, 1, 1, 0, 1, 1, 1, true);
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

        $expectedChecksForTimeLimit = $statusInDatabase == InventoryStatusService::FULL || ($statusInDatabase == InventoryStatusService::PARTIAL) ? 1 : 0;
        $inventoryStatusService->expects($this->exactly($expectedChecksForTimeLimit))->method('hasGoneOverTimeLimit')->willReturn(!isset($mostRecentAttemptTime) || empty(trim($mostRecentAttemptTime)) || $mostRecentAttemptTime == self::TIMESTAMP_OVERDUE);

        if ($startExpected) {
            $inventoryStatusService->expects($this->once())->method('markInventoryStarted')->willReturn(self::TIMESTAMP_NOW);
        } else {
            $inventoryStatusService->expects($this->never())->method('markInventoryStarted');
        }

        $configHelper = $this->createConfigHelper($allItemsActive);
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
        if (isset($expectedExceptionClass) && !empty(trim($expectedExceptionClass) && !isset($exceptionThrownByPageSync))) {
            $inventoryUpdateService->expects($this->never())->method('syncNextPageOfVariations');
        } else {
            $expectedSyncCalls = isset($exceptionThrownByPageSync) ? 1 : count($syncResultsForPages);
            $pageSyncInvocation = $inventoryUpdateService->expects($this->exactly($expectedSyncCalls))->method('syncNextPageOfVariations');

            // TODO: find a way to throw an Exception after processing at least one
            if (isset($exceptionThrownByPageSync)) {
                $pageSyncInvocation->willThrowException($exceptionThrownByPageSync);
            } else {
                $pageSyncInvocation->willReturnOnConsecutiveCalls(...$syncResultsForPages);
                $expectedTimeCalculations = 1;
            }
        }

        $expectedIdleSets = $expectedExceptionClass == InventorySyncInProgressException::class || !$startExpected ? 0 : 1;
        $overridingAPartialWithAFull = $fullInventory && $statusInDatabase == InventoryStatusService::PARTIAL;
        $syncRunning = $statusInDatabase == InventoryStatusService::FULL || $statusInDatabase == InventoryStatusService::PARTIAL;
        $timestampDenotesStaleness = !isset($mostRecentAttemptTime) || empty(trim($mostRecentAttemptTime)) || $mostRecentAttemptTime == self::TIMESTAMP_OVERDUE;
        if ($syncRunning && ($overridingAPartialWithAFull || $timestampDenotesStaleness)) {
            ++$expectedIdleSets;
        }

        $inventoryStatusService->expects($this->exactly($expectedIdleSets))->method('markInventoryIdle');

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

        $inventoryUpdateService->expects($this->exactly($expectedTimeCalculations))->method('calculateTimeSinceSyncStart')->willReturn(self::ELAPSED_TIME);

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

        $expectedCompletionCalls = $completionExpected ? 1 : 0;

        $inventoryStatusService->expects($this->exactly($expectedCompletionCalls))->method('markInventoryComplete');

        /** @var VariationSearchRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $variationSearchRepository = $this->createMock(VariationSearchRepositoryContract::class);

        if ($startExpected) {
            $expectedFilters = [
                'isActive' => true,
            ];

            if (!$allItemsActive) {
                $expectedFilters['referrerId'] = [self::REFERRER_ID];
            }
            $variationSearchRepository->expects($this->once())->method('setFilters')->with($expectedFilters);
        } else {
            $variationSearchRepository->expects($this->never())->method('setFilters');
        }

        $mockPluginApp->willReturn(VariationSearchRepositoryContract::class, [], $variationSearchRepository);

        $actualResult = $inventoryUpdateService->sync($fullInventory);

        if (!$startExpected) {
            $this->assertNull($actualResult, "no result expected");
        } else {
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

    public function dataProviderForTestMainSync()
    {
        $syncPageException = new TestTimeException("Simulated failure when syncing a page of inventory");

        $cases = [];

        $cases[] = ["partial syncs are blocked when no sync of any type is complete v1", false, false, NoReferencePointForPartialInventorySyncException::class, false, ''];
        $cases[] = ["partial syncs are blocked when no sync of any type is complete v2", false, false, NoReferencePointForPartialInventorySyncException::class, false, InventoryStatusService::STATE_IDLE];
        $cases[] = ["partial syncs are blocked when no sync of any type is complete v3", false, false, NoReferencePointForPartialInventorySyncException::class, false, InventoryStatusService::PARTIAL];
        $cases[] = ["partial syncs are blocked when no sync of any type is complete v4", false, false, NoReferencePointForPartialInventorySyncException::class, false, InventoryStatusService::FULL];

        $cases[] = ["partial syncs override a partial sync without a start time v1", true, true, null, false, InventoryStatusService::PARTIAL, null, self::TIMESTAMP_RECENT];

        $cases[] = ["partial syncs override a full sync without a start time", true, true, null, false, InventoryStatusService::FULL, null, self::TIMESTAMP_RECENT];

        $cases[] = ["partial syncs are blocked when a partial sync is running v1", false, false, InventorySyncInProgressException::class, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];

        $cases[] = ["partial syncs are blocked when a full sync is running v1", false, false, InventorySyncInProgressException::class, false, InventoryStatusService::FULL, self::TIMESTAMP_RECENT];

        $cases[] = ["partial syncs can start when a partial sync has been running for too long v1", true, true, null, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT];

        $cases[] = ["partial syncs can start when a full sync has been running for too long v1", true, true, null, false, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT];

        $cases[] = ["full syncs override a partial sync without a start time v1", true, true, null, true, InventoryStatusService::PARTIAL, null, self::TIMESTAMP_RECENT];

        $cases[] = ["full syncs override a full sync without a start time", true, true, null, true, InventoryStatusService::FULL, null, self::TIMESTAMP_RECENT];

        $cases[] = ["full syncs can start when a partial sync has been running for too long v1", true, true, null, true, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT];

        $cases[] = ["full syncs can start when a full sync has been running for too long v1", true, true, null, true, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT];

        $cases[] = ["full syncs can start when a partial sync started recently", true, true, null, true, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];

        $cases[] = ["full syncs are blocked when a full sync started recently", false, false, InventorySyncInProgressException::class, true, InventoryStatusService::FULL, self::TIMESTAMP_RECENT, null];

        $cases[] = ["one page full sync", true, true, null, true, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, null, [new InventoryUpdateResult(true, 10, 9, 10, 0, 6, 5, 4, true)]];

        $cases[] = ["one page partial sync", true, true, null, false, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, [new InventoryUpdateResult(false, 10, 9, 10, 0, 6, 5, 4, true)]];

        $cases[] = ["one page full sync - all items", true, true, null, true, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, null, [new InventoryUpdateResult(true, 10, 9, 10, 0, 6, 5, 4, true)], null, true];

        $cases[] = ["one page partial sync - all items", true, true, null, false, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, [new InventoryUpdateResult(false, 10, 9, 10, 0, 6, 5, 4, true)], null, true];

        $cases[] = ["multiple page full sync", true, true, null, true, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, null, [new InventoryUpdateResult(true, 10, 9, 10, 0, 6, 5, 4, false), new InventoryUpdateResult(true, 8, 7, 8, 0, 1, 2, 3, false), new InventoryUpdateResult(true, 7, 6, 7, 0, 5, 2, 3, true)]];

        $cases[] = ["multiple page partial sync", true, true, null, false, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, [new InventoryUpdateResult(false, 10, 9, 10, 0, 6, 5, 4, false), new InventoryUpdateResult(false, 8, 7, 8, 0, 1, 2, 3, false), new InventoryUpdateResult(false, 7, 6, 7, 0, 5, 2, 3, true)]];

        // can only mock an Exception on first page for now.
        $cases[] = ["full sync should wrap and rethrow arbitrary Exception from syncing page", true, false, InventoryException::class, true, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT, null, [], $syncPageException];

        $cases[] = ["full sync without variations should not flag as complete", true, false, null, true, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, null, [new InventoryUpdateResult(true, 0, 0, 0, 0, 6, 5, 0, true)]];
        $cases[] = ["full sync without stocks should not flag as complete", true, false, null, true, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, null, [new InventoryUpdateResult(true, 0, 1, 0, 0, 6, 5, 0, true)]];

        $cases[] = ["partial sync without variations should not complete", true, false, null, false, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, [new InventoryUpdateResult(false, 0, 0, 0, 0, 6, 5, 0, true)]];
        $cases[] = ["partial sync without stocks should complete", true, true, null, false, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, [new InventoryUpdateResult(false, 0, 1, 0, 0, 6, 5, 0, true)]];

        $cases[] = ["partial sync with failed DTOs should not complete", true, false, null, false, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, [new InventoryUpdateResult(false, 2, 2, 1, 1, 6, 5, 0, true)]];
        $cases[] = ["full sync with failed DTOs should not complete", true, false, null, true, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, [new InventoryUpdateResult(true, 2, 2, 1, 1, 6, 5, 0, true)]];

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
        $expectedExceptionClass = null,
        bool $fullInventory = false,
        $statusInDatabase = InventoryStatusService::STATE_IDLE,
        $mostRecentAttemptTime = null,
        array $variationDataArraysForPage = [],
        array $cannedRequestDtoArraysForVariations = [],
        array $cannedResponseDtosForPage = [],
        $allItemsActive = false,
        bool $lastPage = false,
        int $pageNumber = 1,
        $exceptionThrownByDTOCreation = null,
        $exceptionThrownByDTOSend = null
    ) {
        global $mockPluginApp;

        if (isset($expectedExceptionClass) && !empty($expectedExceptionClass)) {
            $this->expectException($expectedExceptionClass);
        }

        /** @var VariationSearchRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $variationSearchRepository = $this->createMock(VariationSearchRepositoryContract::class);

        $searchesExpected = $pageNumber < 1 || $expectedExceptionClass == InventorySyncInterruptedException::class ? 0 : 1;

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

        $variationSearchRepository->expects($this->exactly($searchesExpected))->method('setSearchParams')->with($expectedSearchParams);

        $pageFactory = new MockPaginatedResultFactory($this, $numVariations);
        $variationSearchRepository->expects($this->exactly($searchesExpected))->method('search')->willReturn($pageFactory->createNext($variationDataArraysForPage, $lastPage));

        $mockPluginApp->willReturn(VariationSearchRepositoryContract::class, [], $variationSearchRepository);

        $totalAmountOfRequestDTOs = 0;
        foreach ($cannedRequestDtoArraysForVariations as $resultArray) {
            $totalAmountOfRequestDTOs += count($resultArray);
        }

        /** @var InventoryMapper&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryMapper = $this->createMock(InventoryMapper::class);

        $creationInvocation = $inventoryMapper->expects($this->exactly($numVariations))->method('createInventoryDTOsFromVariation');
        if (isset($exceptionThrownByDTOCreation)) {
            $creationInvocation->willThrowException($exceptionThrownByDTOCreation);
        } else {
            $creationInvocation->willReturnOnConsecutiveCalls(...$cannedRequestDtoArraysForVariations);
        }

        $numRequestDtos = count($cannedRequestDtoArraysForVariations);
        /** @var InventoryService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryService = $this->createMock(InventoryService::class);

        $sendInvocation = $inventoryService->expects($this->exactly($numRequestDtos))->method('updateBulk');
        if (isset($exceptionThrownByDTOSend)) {
            $sendInvocation->willThrowException($exceptionThrownByDTOSend);
        } else {
            $sendInvocation->willReturnOnConsecutiveCalls(...$cannedResponseDtosForPage);
        }

        /** @var InventoryStatusService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryStatusService = $this->createMock(InventoryStatusService::class);

        $expectedStatusChecks = $pageNumber < 1 ? 0 : 1;
        $inventoryStatusService->expects($this->exactly($expectedStatusChecks))->method('getServiceStatusValue')->willReturn($statusInDatabase);
        $inventoryStatusService->expects($this->exactly($expectedStatusChecks))->method('getStartOfMostRecentAttempt')->willReturn($mostRecentAttemptTime);

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
            self::TIMESTAMP_NOW,
            $variationSearchRepository,
            self::WINDOW_START,
            self::WINDOW_END,
            $this->externalLogs
        );

        if (!isset($expectedExceptionClass) || empty(trim($expectedExceptionClass))) {
            $this->assertNotNull($actualResult);

            $this->assertEquals($lastPage || !isset($variationDataArraysForPage) || empty($variationDataArraysForPage), $actualResult->getLastPage(), "last page flag should match expectation");

            $this->assertEquals($numVariations, $actualResult->getVariationsAttempted(), "amount of variations seen should match the input");

            $expectedDTOCreationAmount = 0;
            if (!isset($exceptionThrownByDTOCreation)) {
                $expectedDTOCreationAmount = $totalAmountOfRequestDTOs;
            }

            $this->assertEquals($expectedDTOCreationAmount, $actualResult->getDtosAttempted(), "amount of DTOs seen should match input");

            $expectedSaveAmount = 0;
            if (!isset($exceptionThrownByDTOCreation) && !isset($exceptionThrownByDTOSend)) {
                $expectedSaveAmount = count($cannedResponseDtosForPage);
            }

            $this->assertEquals($expectedSaveAmount, $actualResult->getDtosSaved(), "amount of DTOs sent should match input");

            // elapsed time is in seconds, so it is often zero!
            // $this->assertGreaterThan(0, $actualResult->getElapsedTime(), "Page sync should have spent some time doing work");

            if ($numVariations > 0 && !isset($exceptionThrownByDTOCreation)) {
                $this->assertGreaterThan(0, $actualResult->getDataGatherMs(), "Page sync should have spent time gathering data");

                if ($totalAmountOfRequestDTOs && !isset($exceptionThrownByDTOSend)) {
                    $this->assertGreaterThan(0, $actualResult->getDataSendMs(), "Page sync should have spent some time sending data");
                } else {
                    $this->assertEquals(0, $actualResult->getDataSendMs(), "Page sync should NOT have spent any time sending data");
                }
            }
        } else {
            $this->assertNull($actualResult);
        }
    }

    public function dataProviderForTestSyncNextPageOfVariations()
    {
        $variationDataFactory = new VariationDataFactory();

        $collectionOneVariation[] = $variationDataFactory->create(1, [12345]);

        $collectionFiveVariations[] = [];
        for ($i = 0; $i < 5; $i++) {
            $collectionFiveVariations[] = $variationDataFactory->create(1, [12345]);
        }

        $cases = [];

        $cases[] = ["no variations - partial - not last", null, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_NOW];
        $cases[] = ["no variations - full - not last", null, true, InventoryStatusService::FULL, self::TIMESTAMP_NOW];

        $cases[] = ["partial sync cancelled", InventorySyncInterruptedException::class, false, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_NOW];
        $cases[] = ["partial sync overridden by partial sync", InventorySyncInterruptedException::class, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial sync overridden by full sync", InventorySyncInterruptedException::class, false, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];

        $cases[] = ["full sync cancelled", InventorySyncInterruptedException::class, true, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_NOW];
        $cases[] = ["full sync overridden by partial sync", InventorySyncInterruptedException::class, true, InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["full sync overridden by full sync", InventorySyncInterruptedException::class, true, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];

        $cases[] = ["page number 0 invalid - partial", InvalidArgumentException::class, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_NOW, [], [], [], false, false, 0];
        $cases[] = ["page number 0 invalid - full", InvalidArgumentException::class, true, InventoryStatusService::FULL, self::TIMESTAMP_NOW, [], [], [], false, false, 0];

        $cases[] = ["page number -1 invalid - partial", InvalidArgumentException::class, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_NOW, [], [], [], false, false, -1];
        $cases[] = ["page number -1 invalid - full", InvalidArgumentException::class, true, InventoryStatusService::FULL, self::TIMESTAMP_NOW, [], [], [], false, false, -1];

        $cases[] = ["page number -5 invalid - partial", InvalidArgumentException::class, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_NOW, [], [], [], false, false, -5];
        $cases[] = ["page number -5 invalid - full", InvalidArgumentException::class, true, InventoryStatusService::FULL, self::TIMESTAMP_NOW, [], [], [], false, false, -5];

        $cases[] = ["exception thrown at data gather - partial", null, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_NOW, $collectionOneVariation, [], [], false, false, 1, new TestTimeException("DTO gather failure")];
        $cases[] = ["exception thrown at data gather - full", null, true, InventoryStatusService::FULL, self::TIMESTAMP_NOW, $collectionOneVariation, [], [], false, false, 1, new TestTimeException("DTO gather failure")];

        $cases[] = ["exception thrown at data send - partial", null, false, InventoryStatusService::PARTIAL, self::TIMESTAMP_NOW, $collectionOneVariation, [[new RequestDTO()]], [], false, false, 1, null, new TestTimeException("DTO send failure")];
        $cases[] = ["exception thrown at data send - full", null, true, InventoryStatusService::FULL, self::TIMESTAMP_NOW, $collectionOneVariation, [[new RequestDTO()]], [], false, false, 1, null, new TestTimeException("DTO send failure")];

        // TODO: accounting for errors in ResponseDTOs after sending data

        // TODO: positive (more than 1) amounts of Variations combined with various results from InventoryMapper->createInventoryDTOsFromVariation

        // TODO: positive (more than 1) amounts of Variations, positive results from InventoryMapper->createInventoryDTOsFromVariation, various results from InventoryService->updateBulk

        return $cases;
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
