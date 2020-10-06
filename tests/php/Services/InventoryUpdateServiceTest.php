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
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
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
    const W3C_LATER = '2020-10-06T15:44:02+00:00';

    /**
     * Test the partial sync window calculations
     *
     * @param mixed $expected
     * @return void
     *
     * @dataProvider dataProviderForTestGetStartOfDeltaSyncWindow
     */
    public function testGetStartOfDeltaSyncWindow($expected, $lastCompletionStartPartial, $lastCompletionStartFull)
    {
        $inventoryStatusService = $this->createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull);

        if (!isset($expected) || empty($expected)) {
            $this->expectException(InventorySyncBlockedException::class);
        }

        $result = InventoryUpdateService::getStartOfDeltaSyncWindow($inventoryStatusService);

        $this->assertEquals($expected, $result);
    }

    public function dataProviderForTestGetStartOfDeltaSyncWindow()
    {

        $cases[] = [null, '', ''];

        $cases[] = [self::W3C_LATER, self::TIMESTAMP_LATER, ''];

        $cases[] = [self::W3C_LATER, '', self::TIMESTAMP_LATER];

        $cases[] = [self::W3C_LATER, self::TIMESTAMP_LATER, self::TIMESTAMP_EARLIER];

        $cases[] = [self::W3C_LATER, self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER];

        $cases[] = [self::W3C_LATER, self::TIMESTAMP_LATER, self::TIMESTAMP_LATER];

        return $cases;
    }

    /**
     * Test Inventory Sync
     *
     * @param boolean $fullInventory
     * @param InventoryUpdateResult $expectedResult
     * @param array $cannedRequestDtos
     * @param array $cannedResponseDtos
     * @param array $cannedVariationDataArrays
     * @return void
     *
     * @dataProvider dataProviderForTestSync
     */
    public function testSync(
        $expectedResult,
        $expectedExceptionClass,
        bool $fullInventory,
        array $cannedRequestDtos,
        array $cannedResponseDtos,
        array $cannedVariationDataArrays,
        $lastCompletionStartPartial,
        $lastCompletionStartFull
    ) {
        $inventoryUpdateService = $this->createInventoryUpdateService(
            $cannedRequestDtos,
            $cannedResponseDtos,
            $cannedVariationDataArrays,
            $lastCompletionStartPartial,
            $lastCompletionStartFull
        );

        if (isset($expectedExceptionClass) && !empty($expectedExceptionClass)) {
            $this->expectException($expectedExceptionClass);
        }

        $actualResult = $inventoryUpdateService->sync($fullInventory);

        if (isset($actualResult) && isset($expectedResult))
        {
            // hack so that we can use built-in PHP equality
            $expectedResult->setElapsedTime($actualResult->getElapsedTime());
        }

        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataProviderForTestSync()
    {
        $emptyResult = new InventoryUpdateResult();

        $cases = [];

        $cases[] = [$emptyResult, null, false, [], [], [], self::TIMESTAMP_EARLIER, self::TIMESTAMP_LATER];

        // TODO: populate cases

        return $cases;
    }

    private function createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull): InventoryStatusService
    {
        /** @var InventoryStatusService&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryStatusService = $this->createMock(InventoryStatusService::class);
        $inventoryStatusService->method('getLastCompletionStart')->willReturnMap([
            [false, $lastCompletionStartPartial],
            [true, $lastCompletionStartFull]
        ]);

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
        $lastCompletionStartFull
    ): InventoryUpdateService {
        /** @var InventoryUpdateResultFactory&\PHPUnit\Framework\MockObject\MockObject */
        $inventoryUpdateResultFactory = $this->createMock(InventoryUpdateResultFactory::class);
        $inventoryUpdateResultFactory->method(self::METHOD_CREATE)->willReturn(new InventoryUpdateResult());

        $inventoryService = $this->createInventoryService($cannedResponseDtos);

        $inventoryMapper = $this->createInventoryMapper($cannedRequestDtos);

        $statusService = $this->createInventoryStatusService($lastCompletionStartPartial, $lastCompletionStartFull);

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
