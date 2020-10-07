<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

require_once(dirname(__DIR__) . DIRECTORY_SEPARATOR
    . 'lib' . DIRECTORY_SEPARATOR
    . 'plentymockets' . DIRECTORY_SEPARATOR
    . 'Factories' . DIRECTORY_SEPARATOR
    . 'MockVariationSearchRepositoryFactory.php');

use Wayfair\Core\Api\Services\InventoryService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\InventorySyncInProgressException;
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Core\Exceptions\NoReferencePointForPartialInventorySyncException;
use Wayfair\Factories\ExternalLogsFactory;
use Wayfair\Factories\InventoryUpdateResultFactory;
use Wayfair\Factories\VariationSearchRepositoryFactory;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Models\InventoryUpdateResult;
use Wayfair\Services\InventoryStatusService;
use Wayfair\Services\InventoryUpdateService;
use Wayfair\PlentyMockets\Factories\MockVariationSearchRepositoryFactory;

final class InventoryUpdateServiceTest extends \PHPUnit\Framework\TestCase
{
    const METHOD_CREATE = 'create';

    const TIMESTAMP_OVERDUE = '2020-10-05 12:34:02.000000 +02:00';
    const TIMESTAMP_RECENT = '2020-10-06 17:44:02.000000 +02:00';
    const TIMESTAMP_NOW = '2020-10-06 17:45:02.000000 +02:00';
    const TIMESTAMP_FUTURE = '2035-10-06 17:44:02.000000 +02:00';
    const W3C_LATER = '2020-10-06T15:44:02+00:00';

    /**
     * Test the partial sync window calculations
     *
     * @param mixed $expected
     * @return void
     *
     * @dataProvider dataProviderForTestGetStartOfDeltaSyncWindow
     */
    public function testGetStartOfDeltaSyncWindow($name, $expected, $lastCompletionStartPartial, $lastCompletionStartFull)
    {
        $inventoryStatusService = $this->createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull, InventoryStatusService::STATE_IDLE);

        if (!isset($expected) || empty($expected)) {
            $this->expectException(InventorySyncBlockedException::class);
        }

        $result = InventoryUpdateService::getStartOfDeltaSyncWindow($inventoryStatusService);

        $this->assertEquals($expected, $result, $name);
    }

    public function dataProviderForTestGetStartOfDeltaSyncWindow()
    {

        $cases[] = ['Lack of data should cause exception', null, '', ''];

        $cases[] = ['lack of full sync should cause exception', null, self::TIMESTAMP_RECENT, ''];

        $cases[] = ['knowing only full sync should return full sync time', self::W3C_LATER, '', self::TIMESTAMP_RECENT];

        $cases[] = ['partial sync happening more recently than full sync should return partial sync time', self::W3C_LATER, self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE];

        $cases[] = ['full sync happening more recently than partial sync should return full sync time', self::W3C_LATER, self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT];

        $cases[] = ['both kinds of sync happening at the same time should return that duplicated time', self::W3C_LATER, self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT];

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
        array $cannedVariationDataArrays,
        $lastCompletionStartPartial,
        $lastCompletionStartFull,
        $currentInventoryStatus,
        $currentStartTime = null
    ) {
        $inventoryUpdateService = $this->createInventoryUpdateService(
            $cannedRequestDtos,
            $cannedResponseDtos,
            $cannedVariationDataArrays,
            $lastCompletionStartPartial,
            $lastCompletionStartFull,
            $currentInventoryStatus,
            $currentStartTime
        );

        if (isset($expectedExceptionClass) && !empty($expectedExceptionClass)) {
            $this->expectException($expectedExceptionClass);
        }

        $actualResult = $inventoryUpdateService->sync($fullInventory);

        if (isset($actualResult) && isset($expectedResult)) {
            // hack so that we can use built-in PHP equality
            $expectedResult->setElapsedTime($actualResult->getElapsedTime());
        }

        $this->assertEquals($expectedResult, $actualResult, $name);
    }

    public function dataProviderForTestSync()
    {
        $emptyResultPartial = new InventoryUpdateResult();
        $emptyResultFull = new InventoryUpdateResult();
        $emptyResultFull->setFullInventory(true);

        $cases = [];

        $cases[] = ["no variations should cause a partial sync to clean exit", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE];
        $cases[] = ["no variations should cause a full sync to clean exit", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE];

        $cases[] = ["partial sync should stop if any sync starts running while it is running", null, InventorySyncInterruptedException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_FUTURE];
        $cases[] = ["full sync should should stop if any sync starts running while it is running", null, InventorySyncInterruptedException::class, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_FUTURE];

        $cases[] = ["partial syncs are blocked when no sync of any type is recorded", null, InventorySyncBlockedException::class, false, [], [], [], '', '', InventoryStatusService::STATE_IDLE];

        $cases[] = ["partial syncs are blocked when no full sync has been recorded v1", null, InventorySyncBlockedException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::STATE_IDLE];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v2", null, InventorySyncBlockedException::class, false, [], [], [], '', '', InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v3", null, NoReferencePointForPartialInventorySyncException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v4", null, NoReferencePointForPartialInventorySyncException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs are blocked when no full sync has been recorded v5", null, NoReferencePointForPartialInventorySyncException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];

        $cases[] = ["partial syncs are blocked when a partial sync is running v1", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v2", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v3", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v4", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v5", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v6", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v7", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a partial sync is running v8", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v9", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v10", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v11", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v12", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v13", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a partial sync is running v14", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_FUTURE];

        $cases[] = ["partial syncs are blocked when a full sync is running v1", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::FULL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v2", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v3", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v4", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v5", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::FULL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v6", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v7", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_RECENT];
        $cases[] = ["partial syncs are blocked when a full sync is running v8", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v9", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v10", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v11", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v12", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v13", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v14", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];

        $cases[] = ["partial syncs can start when a partial sync has been running for too long v1", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a partial sync has been running for too long v2", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a partial sync has been running for too long v3", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a partial sync has been running for too long v4", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];

        $cases[] = ["partial syncs can start when a full sync has been running for too long v1", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a full sync has been running for too long v2", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a full sync has been running for too long v3", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["partial syncs can start when a full sync has been running for too long v4", $emptyResultPartial, null, false, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];


        $cases[] = ["full syncs can start when a partial sync has been running for too long v1", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a partial sync has been running for too long v2", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a partial sync has been running for too long v3", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a partial sync has been running for too long v4", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_OVERDUE];

        $cases[] = ["full syncs can start when a full sync has been running for too long v1", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a full sync has been running for too long v2", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a full sync has been running for too long v3", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];
        $cases[] = ["full syncs can start when a full sync has been running for too long v4", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::FULL, self::TIMESTAMP_OVERDUE];

        $cases[] = ["full syncs can start when a partial sync is running v1", $emptyResultFull, null, true, [], [], [], '', '', InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v2", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, '', InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v3", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v4", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_OVERDUE, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v5", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, '', InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v6", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_OVERDUE, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];
        $cases[] = ["full syncs can start when a partial sync is running v7", $emptyResultFull, null, true, [], [], [], self::TIMESTAMP_RECENT, self::TIMESTAMP_RECENT, InventoryStatusService::PARTIAL, self::TIMESTAMP_RECENT];



        // TODO: single page of Variations
        // TODO: two or more pages of Variations
        // TODO: No inventory found for Variations (no RequestDTOs out of InventoryMapper->createInventoryDTOsFromVariation)
        // TODO: No ResponseDTOs from InventoryService->updateBulk

        // TODO: find a way to pepper in a timestamp changes to test interruption on page 2+ ?
        // TODO: Inventory Service failures?
        // TODO: Variation Data that is out of bounds?
        // TODO: Inventory Data that is out of bounds?
        // TODO: incomplete Variation Data?
        // TODO: incomplete Inventory Data?


        // TODO: load testing in separate file that does not run during normal test suite


        // TODO: maximum pages of Variations (if implemented)

        return $cases;
    }

    private function createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull, $currentInventoryStatus, $currentStartTime = null): InventoryStatusService
    {
        /** @var InventoryStatusService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryStatusService = $this->createMock(InventoryStatusService::class);
        $inventoryStatusService->method('getLastCompletionStart')->willReturnMap([
            [false, $lastCompletionStartPartial],
            [true, $lastCompletionStartFull],
        ]);

        $inventoryStatusService->method('getServiceStatusValue')->willReturn($currentInventoryStatus);

        $inventoryStatusService->method('getStartOfMostRecentAttempt')->willReturn($currentStartTime);

        $overDue = $currentStartTime == self::TIMESTAMP_OVERDUE;
        $overLimit = $overDue && isset($currentInventoryStatus) && '' != $currentInventoryStatus &&  InventoryStatusService::STATE_IDLE != $currentInventoryStatus;
        $inventoryStatusService->method('isOverdue')->willReturn($overDue);
        $inventoryStatusService->method('hasGoneOverTimeLimit')->willReturn($overLimit);
        $inventoryStatusService->method('markInventoryStarted')->willReturn(self::TIMESTAMP_NOW);

        return $inventoryStatusService;
    }

    private function createInventoryMapper(array $cannedRequestDtos): InventoryMapper
    {
        /** @var InventoryMapper&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryMapper = $this->createMock(InventoryMapper::class);
        $inventoryMapper->method('createInventoryDTOsFromVariation')->willReturnOnConsecutiveCalls(...$cannedRequestDtos);

        return $inventoryMapper;
    }

    private function createInventoryService(array $cannedResponseDtos): InventoryService
    {
        /** @var InventoryService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryService = $this->createMock(InventoryService::class);
        $inventoryService->method('updateBulk')->willReturnOnConsecutiveCalls(...$cannedResponseDtos);

        return $inventoryService;
    }

    private function createVariationSearchRepositoryFactory(array $cannedVariationDataArrays): VariationSearchRepositoryFactory
    {
        $variationSearchRepository = (new MockVariationSearchRepositoryFactory($this))->create($cannedVariationDataArrays);

        /** @var VariationSearchRepositoryFactory&\PHPUnit\Framework\MockObject\MockObject */
        $variationSearchRepositoryFactory = $this->createMock(VariationSearchRepositoryFactory::class);
        $variationSearchRepositoryFactory->method(self::METHOD_CREATE)->willReturn($variationSearchRepository);

        return $variationSearchRepositoryFactory;
    }

    private function createInventoryUpdateService(
        array $cannedRequestDtos,
        array $cannedResponseDtos,
        array $cannedVariationDataArrays,
        $lastCompletionStartPartial,
        $lastCompletionStartFull,
        $currentInventoryStatus,
        $currentStartTime = null
    ): InventoryUpdateService {
        /** @var InventoryUpdateResultFactory&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryUpdateResultFactory = $this->createMock(InventoryUpdateResultFactory::class);
        $inventoryUpdateResultFactory->method(self::METHOD_CREATE)->willReturn(new InventoryUpdateResult());

        $inventoryService = $this->createInventoryService($cannedResponseDtos);

        $inventoryMapper = $this->createInventoryMapper($cannedRequestDtos);

        $statusService = $this->createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull, $currentInventoryStatus, $currentStartTime);

        /** @var ConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
        $configHelper = $this->createMock(ConfigHelper::class);

        /** @var LoggerContract&\PHPUnit\Framework\MockObject\MockObject */
        $logger = $this->createMock(LoggerContract::class);

        /** @var LogSenderService&\PHPUnit\Framework\MockObject\MockObject */
        $logSenderService = $this->createMock(LogSenderService::class);

        /** @var ExternalLogsFactory&\PHPUnit\Framework\MockObject\MockObject */
        $externalLogsFactory = $this->createMock(ExternalLogsFactory::class);
        $externalLogsFactory->method(self::METHOD_CREATE)->willReturn(new ExternalLogs());

        $variationSearchRepositoryFactory = $this->createVariationSearchRepositoryFactory($cannedVariationDataArrays);

        /** @var InventoryUpdateResultFactory&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryUpdateResultFactory = $this->createMock(InventoryUpdateResultFactory::class);
        $inventoryUpdateResultFactory->method(self::METHOD_CREATE)->willReturn(new InventoryUpdateResult());

        return new InventoryUpdateService(
            $inventoryService,
            $inventoryMapper,
            $statusService,
            $configHelper,
            $logger,
            $logSenderService,
            $externalLogsFactory,
            $variationSearchRepositoryFactory,
            $inventoryUpdateResultFactory
        );
    }
}
