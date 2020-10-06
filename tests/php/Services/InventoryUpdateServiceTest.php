<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Tests\Mappers;

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Plenty\Repositories\Models\PaginatedResult;
use Wayfair\Core\Api\Services\InventoryService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Exceptions\InventorySyncInProgressException;
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Factories\ExternalLogsFactory;
use Wayfair\Factories\InventoryUpdateResultFactory;
use Wayfair\Factories\VariationSearchRepositoryFactory;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Models\InventoryUpdateResult;
use Wayfair\Services\InventoryStatusService;
use Wayfair\Services\InventoryUpdateService;

final class InventoryUpdateServiceTest extends \PHPUnit\Framework\TestCase
{
    const METHOD_CREATE = 'create';

    const TIMESTAMP_EARLIER = '2020-10-05 12:34:02.000000 +02:00';
    const TIMESTAMP_LATER = '2020-10-06 17:44:02.000000 +02:00';
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

        $cases[] = ['lack of full sync should cause exception', null, self::TIMESTAMP_LATER, ''];

        $cases[] = ['knowing only full sync should return full sync time', self::W3C_LATER, '', self::TIMESTAMP_LATER];

        $cases[] = ['partial sync happening more recently than full sync should return partial sync time', self::W3C_LATER, self::TIMESTAMP_LATER, self::TIMESTAMP_EARLIER];

        $cases[] = ['full sync happening more recently than partial sync should return full sync time', self::W3C_LATER, self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER];

        $cases[] = ['both kinds of sync happening at the same time should return that duplicated time', self::W3C_LATER, self::TIMESTAMP_LATER, self::TIMESTAMP_LATER];

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
        $emptyResult = new InventoryUpdateResult();

        $cases = [];

        $cases[] = ["no variations should cause a clean exit", $emptyResult, null, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER, InventoryStatusService::STATE_IDLE];

        $cases[] = ["partial sync should be interrupted by a new sync", null, InventorySyncInterruptedException::class, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_FUTURE];
        $cases[] = ["full sync should be interrupted by a new sync", null, InventorySyncInterruptedException::class, true, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER, InventoryStatusService::STATE_IDLE, self::TIMESTAMP_FUTURE];

        $cases[] = ["partial syncs are blocked when no sync of any type is recorded", null, InventorySyncBlockedException::class, false, [], [], [], '', '', InventoryStatusService::STATE_IDLE];

        $cases[] = ["partial syncs are blocked when no full sync has been recorded", null, InventorySyncBlockedException::class, false, [], [], [], self::TIMESTAMP_LATER, '', InventoryStatusService::STATE_IDLE];

        $cases[] = ["partial syncs are blocked when a full sync is running v1", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::FULL, self::TIMESTAMP_EARLIER];
        $cases[] = ["partial syncs are blocked when a full sync is running v2", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, '', InventoryStatusService::FULL, self::TIMESTAMP_EARLIER];
        $cases[] = ["partial syncs are blocked when a full sync is running v3", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_EARLIER, InventoryStatusService::FULL, self::TIMESTAMP_EARLIER];
        $cases[] = ["partial syncs are blocked when a full sync is running v4", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER, InventoryStatusService::FULL, self::TIMESTAMP_EARLIER];
        $cases[] = ["partial syncs are blocked when a full sync is running v5", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, '', InventoryStatusService::FULL, self::TIMESTAMP_EARLIER];
        $cases[] = ["partial syncs are blocked when a full sync is running v6", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, self::TIMESTAMP_EARLIER, InventoryStatusService::FULL, self::TIMESTAMP_EARLIER];
        $cases[] = ["partial syncs are blocked when a full sync is running v7", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, self::TIMESTAMP_LATER, InventoryStatusService::FULL, self::TIMESTAMP_EARLIER];

        $cases[] = ["partial syncs are blocked when a full sync is running v8", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::FULL, self::TIMESTAMP_LATER];
        $cases[] = ["partial syncs are blocked when a full sync is running v9", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, '', InventoryStatusService::FULL, self::TIMESTAMP_LATER];
        $cases[] = ["partial syncs are blocked when a full sync is running v10", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_EARLIER, InventoryStatusService::FULL, self::TIMESTAMP_LATER];
        $cases[] = ["partial syncs are blocked when a full sync is running v11", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER, InventoryStatusService::FULL, self::TIMESTAMP_LATER];
        $cases[] = ["partial syncs are blocked when a full sync is running v12", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, '', InventoryStatusService::FULL, self::TIMESTAMP_LATER];
        $cases[] = ["partial syncs are blocked when a full sync is running v13", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, self::TIMESTAMP_EARLIER, InventoryStatusService::FULL, self::TIMESTAMP_LATER];
        $cases[] = ["partial syncs are blocked when a full sync is running v14", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, self::TIMESTAMP_LATER, InventoryStatusService::FULL, self::TIMESTAMP_LATER];

        $cases[] = ["partial syncs are blocked when a full sync is running v15", null, InventorySyncInProgressException::class, false, [], [], [], '', '', InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v16", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, '', InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v17", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_EARLIER, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v18", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v19", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, '', InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v20", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, self::TIMESTAMP_EARLIER, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];
        $cases[] = ["partial syncs are blocked when a full sync is running v21", null, InventorySyncInProgressException::class, false, [], [], [], self::TIMESTAMP_LATER, self::TIMESTAMP_LATER, InventoryStatusService::FULL, self::TIMESTAMP_FUTURE];

        // TODO: partial sync blocked by partial sync that has not timed out yet
        // TODO: overriding a partial sync that has timed out, with a partial sync
        // TODO: full sync should never be blocked by partial sync
        // TODO: interrupting a partial sync with a full sync (use the FUTURE timestamp)
        // TODO: interrupting a full sync with a new full sync (use the FUTURE timestamp)

        // TODO: find a way to pepper in a timestamp changes to test interruption on a page in the middle of the sync (instead of page 1)

        // TODO: single page of Variations
        // TODO: multiple pages of Variations
        // TODO: No inventory found for Variations
        // TODO: Inventory Service failures?
        // TODO: incomplete Variation Data?
        // TODO: incomplete Inventory Data?


        // TODO: load testing in separate file that does not run during normal test suite

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

    private function createPaginatedResult(array $searchResults, bool $isLastPage): PaginatedResult
    {
        /** @var PaginatedResult&\PHPUnit\Framework\MockObject\MockObject */
        $paginatedResult = $this->createMock(PaginatedResult::class);
        $paginatedResult->method('getResult')->willReturn($searchResults);
        $paginatedResult->method('isLastPage')->willReturn($isLastPage);

        return $paginatedResult;
    }

    private function createVariationSearchRepository(array $cannedVariationDataArrays): VariationSearchRepositoryContract
    {
        /** @var VariationSearchRepositoryContract&\PHPUnit\Framework\MockObject\MockObject */
        $variationSearchRepository = $this->createMock(VariationSearchRepositoryContract::class);

        $pages = [];
        $amtPages = count($cannedVariationDataArrays);

        $page = 0;
        while ($page++ < $amtPages - 1) {
            $pages[] = $this->createPaginatedResult($cannedVariationDataArrays[$page], false);
        }

        $dataForLastPage = [];

        // put in the last page
        if ($amtPages > 0) {
            $dataForLastPage = $cannedVariationDataArrays[$amtPages - 1];
        }

        // there's always at least one page returned by Plenty, even if there are no results.
        // the last page MUST report that it is the last page, or there will be infinite looping!
        $pages[] = $this->createPaginatedResult($dataForLastPage, true);

        $variationSearchRepository->method('search')->willReturnOnConsecutiveCalls(...$pages);

        return $variationSearchRepository;
    }

    private function createVariationSearchRepositoryFactory(array $cannedVariationDataArrays): VariationSearchRepositoryFactory
    {
        $variationSearchRepository = $this->createVariationSearchRepository($cannedVariationDataArrays);

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
