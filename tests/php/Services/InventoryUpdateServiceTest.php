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
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\InventorySyncInProgressException;
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Core\Exceptions\NoReferencePointForPartialInventorySyncException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Mappers\InventoryMapper;
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
        $inventoryStatusService = $this->createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull, InventoryStatusService::STATE_IDLE);

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
        $expectedResult,
        $expectedExceptionClass,
        bool $fullInventory,
        array $cannedRequestDtos,
        array $cannedResponseDtos,
        array $variationDataArraysForPages,
        $lastCompletionStartPartial,
        $lastCompletionStartFull,
        $currentInventoryStatus,
        $allItemsActive = false,
        $mostRecentAttemptTime = null
    ) {
        $shouldStartSyncing = (!isset($expectedExceptionClass) || empty($expectedExceptionClass) || ($expectedExceptionClass == InventorySyncInterruptedException::class && count($variationDataArraysForPages)));

        $inventoryUpdateService = $this->createInventoryUpdateService(
            $fullInventory,
            $shouldStartSyncing,
            $cannedRequestDtos,
            $cannedResponseDtos,
            $variationDataArraysForPages,
            $lastCompletionStartPartial,
            $lastCompletionStartFull,
            $currentInventoryStatus,
            $allItemsActive,
            $mostRecentAttemptTime
        );

        if (isset($expectedExceptionClass) && !empty($expectedExceptionClass)) {
            $this->expectException($expectedExceptionClass);
        }

        $actualResult = $inventoryUpdateService->sync($fullInventory);

        if (isset($actualResult) && isset($expectedResult)) {

            if ($actualResult->getElapsedTime() <= 0) {
                $this->fail("Sync method should have spent some time doing work");
            }

            // hack so that we can use built-in object equality
            $expectedResult->setElapsedTime($actualResult->getElapsedTime());
        }

        $this->assertEquals($expectedResult, $actualResult, $name);
    }

    public function dataProviderForTestSync()
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

        $cases[] = ["full syncs can start when the first full sync has been running for too long", $emptyResultFull, null, true, [], [], [], null, null, InventoryStatusService::FULL, false, self::TIMESTAMP_RECENT];

        // TODO: make sure sync method returns all DTOs that InventoryService returns to it

        // TODO: make sure sync method returns correct summation of failures

        // TODO: make sure sync method returns correct summation of successfully sent DTOs

        // TODO: No ResponseDTOs from InventoryService->updateBulk

        // TODO: find a way to pepper in a timestamp changes to test interruption on page 2+ ?

        return $cases;
    }

    private function createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull, $currentInventoryStatus, $mostRecentAttemptTime = null): InventoryStatusService
    {
        /** @var InventoryStatusService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryStatusService = $this->createMock(InventoryStatusService::class);
        $inventoryStatusService->method('getLastCompletionStart')->willReturnMap([
            [false, $lastCompletionStartPartial],
            [true, $lastCompletionStartFull],
        ]);

        $inventoryStatusService->method('getServiceStatusValue')->willReturn($currentInventoryStatus);

        $inventoryStatusService->method('getStartOfMostRecentAttempt')->willReturn($mostRecentAttemptTime);

        $overDue = $mostRecentAttemptTime == self::TIMESTAMP_OVERDUE;
        $overLimit = $overDue && isset($currentInventoryStatus) && '' != $currentInventoryStatus &&  InventoryStatusService::STATE_IDLE != $currentInventoryStatus;
        $inventoryStatusService->method('isOverdue')->willReturn($overDue);
        $inventoryStatusService->method('hasGoneOverTimeLimit')->willReturn($overLimit);
        $inventoryStatusService->method('markInventoryStarted')->willReturn(self::TIMESTAMP_NOW);

        return $inventoryStatusService;
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

    private function createVariationSearchRepository(array $variationDataArraysForPages, bool $allItemsActive, bool $shouldStartSyncing): VariationSearchRepositoryContract
    {
        $expectedFilters = null;

        if ($shouldStartSyncing) {
            $expectedFilters = ['isActive' => true];
            if (!$allItemsActive) {
                // referrerId is a plural input
                $expectedFilters['referrerId'] = [self::REFERRER_ID];
            }
        }

        $expectedSearches = 0;
        if ($shouldStartSyncing) {
            $expectedSearches = count($variationDataArraysForPages);
            if ($expectedSearches < 1) {
                // even if we are not supplying data we're expecting to search once and get an empty page
                $expectedSearches = 1;
            }
        }

        // multiple Variations per page - Variation data is returned as arrays
        return (new MockVariationSearchRepositoryFactory($this))->create($variationDataArraysForPages, $expectedFilters, $expectedSearches);
    }

    private function createInventoryUpdateService(
        bool $fullInventory,
        bool $shouldStartSyncing,
        array $cannedRequestDtos,
        array $cannedResponseDtos,
        array $variationDataArraysForPages,
        $lastCompletionStartPartial,
        $lastCompletionStartFull,
        $currentInventoryStatus,
        bool $allItemsActive,
        $mostRecentAttemptTime = null
    ): InventoryUpdateService {

        global $mockPluginApp;
        $mockPluginApp = new MockPluginApp($this);

        // FIXME: needs to return subsequent Result objects
        $mockPluginApp->willReturn(InventoryUpdateResult::class, [], new InventoryUpdateResult());

        $inventoryService = $this->createInventoryService($cannedResponseDtos);

        $numVariations = 0;
        foreach ($variationDataArraysForPages as $page) {
            $numVariations += count($page);
        }

        $inventoryMapper = $this->createInventoryMapper($cannedRequestDtos, $numVariations);

        $statusService = $this->createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull, $currentInventoryStatus, $mostRecentAttemptTime);

        /** @var AbstractConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
        $configHelper = $this->createMock(AbstractConfigHelper::class);
        $configHelper->method('getOrderReferrerValue')->willReturn(self::REFERRER_ID);
        $configHelper->method('isAllItemsActive')->willReturn($allItemsActive);

        /** @var LoggerContract&\PHPUnit\Framework\MockObject\MockObject */
        $logger = new TestTimeLogger();

        /** @var LogSenderService&\PHPUnit\Framework\MockObject\MockObject */
        $logSenderService = $this->createMock(LogSenderService::class);

        $variationSearchRepository = $this->createVariationSearchRepository($variationDataArraysForPages, $allItemsActive, $shouldStartSyncing);
        $mockPluginApp->willReturn(VariationSearchRepositoryContract::class, [], $variationSearchRepository);

        /** @var InventoryUpdateService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryUpdateService = $this->createTestProxy(InventoryUpdateService::class, [
            $inventoryService,
            $inventoryMapper,
            $statusService,
            $configHelper,
            $logger,
            $logSenderService
        ]);

        $expectedWindowCalculations = (!$shouldStartSyncing || $fullInventory || count($variationDataArraysForPages) < 1) ? 0 : 1;
        $inventoryUpdateService->expects($this->exactly($expectedWindowCalculations))->method('getStartOfDeltaSyncWindow');
        $inventoryUpdateService->expects($this->exactly($expectedWindowCalculations))->method('getEndOfDeltaSyncWindow');

        return $inventoryUpdateService;
    }
}
