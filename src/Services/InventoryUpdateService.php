<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\Core\Api\Services\InventoryService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\TimeHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Models\ExternalLogs;

/**
 * Service module for sending inventory updates to Wayfair
 */
class InventoryUpdateService
{
  const LOG_KEY_DEBUG = 'debugInventoryUpdate';
  const LOG_KEY_INVALID_INVENTORY_DTO = 'invalidInventoryDto';
  const LOG_KEY_INVALID_STOCK_BUFFER = 'invalidStockBufferValue';
  const LOG_KEY_NO_SYNCS = 'noInventorySyncs';

  const LOG_KEY_END_FULL = 'fullInventoryEnd';
  const LOG_KEY_END_PARTIAL = 'partialInventoryEnd';
  const LOG_KEY_FAILED_FULL = 'fullInventoryFailed';
  const LOG_KEY_FAILED_PARTIAL = 'partialInventoryFailed';
  const LOG_KEY_INTERRUPTED_FULL = 'fullInventoryInterrupted';
  const LOG_KEY_INTERRUPTED_PARTIAL = 'partialInventoryInterrupted';
  const LOG_KEY_LONG_RUN_FULL = 'fullInventoryLongRunning';
  const LOG_KEY_LONG_RUN_PARTIAL = 'partialInventoryLongRunning';
  const LOG_KEY_START_FULL = 'fullInventoryStart';
  const LOG_KEY_START_PARTIAL = 'partialInventoryStart';
  const LOG_KEY_SKIPPED_FULL = 'fullInventorySkipped';
  const LOG_KEY_SKIPPED_PARTIAL = 'partialInventorySkipped';

  // TODO: make these user-configurable in a future update
  const MAX_INVENTORY_TIME_FULL = 14400;
  const MAX_INVENTORY_TIME_PARTIAL = 3600;

  const INVENTORY_SAVE_TOTAL = 'inventorySaveTotal';
  const INVENTORY_SAVE_SUCCESS = 'inventorySaveSuccess';
  const INVENTORY_SAVE_FAIL = 'inventorySaveFail';
  const SAVE_INVENTORY_DURATION = 'saveInventoryDuration';
  const SAVED_INVENTORY_DURATION = 'savedInventoryDuration';
  const PAGES = 'pages';
  const ERROR_MESSAGE = 'errorMessage';

  const VARIATIONS_PER_PAGE = 200;

  /** @var InventoryStatusService */
  private $statusService;

  /** @var InventoryService */
  private $inventoryService;

  /** @var InventoryMapper */
  private $inventoryMapper;

  /** @var LoggerContract */
  private $logger;

  /** @var AbstractConfigHelper */
  private $configHelper;

  /** @var LogSenderService */
  private $logSenderService;

  public function __construct(
    InventoryService $inventoryService,
    InventoryMapper $inventoryMapper,
    InventoryStatusService $statusService,
    AbstractConfigHelper $configHelper,
    LoggerContract $logger,
    LogSenderService $logSenderService
  ) {
    $this->inventoryService = $inventoryService;
    $this->inventoryMapper = $inventoryMapper;
    $this->statusService = $statusService;
    $this->configHelper = $configHelper;
    $this->logger = $logger;
    $this->logSenderService = $logSenderService;
  }

  /**
   * Send inventory updates from Plentymarkets to Wayfair.
   *
   * @param bool $fullInventory flag for syncing entire inventory versus partial inventory update
   * @param bool $manual flag used during logging which indicates that the sync was manually induced
   *
   * @return array
   */
  public function sync(bool $fullInventory, bool $manual = false): array
  {
    $startTimeStamp = null;

    $totalDtosAttempted = 0;
    $totalDtosSaved = 0;
    $totalDtosFailed = 0;
    $totalTimeSpentGatheringData = 0;
    $totalTimeSpentSendingData = 0;
    $totalVariationIdsInDTOsForAllPages = 0;

    /** @var ExternalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    try {

      if ($this->syncIsBlocked($fullInventory)) {
        throw new InventorySyncBlockedException("Another inventory sync is in progress, preventing this one from starting");
      }

      if (!$fullInventory) {
        $lastGoodFullSyncStart = $this->statusService->getLastSuccessfulAttemptTime(true);
        if (!isset($lastGoodFullSyncStart) || empty($lastGoodFullSyncStart)) {
          $this->logger->info(
            TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SYNCS),
            [
              'additionalInfo' => [],
              'method' => __METHOD__
            ]
          );

          // NOTICE: due to recursion, this log will show up AFTER the logs for the Full Sync!
          $externalLogs->addInfoLog("Forced a full inventory sync as there are no successful full syncs on record");

          return $this->sync(true, $manual);
        }
      }

      // look up item mapping method at this level to ensure consistency and improve efficiency
      $itemMappingMethod = $this->configHelper->getItemMappingMethod();

      // TODO: remove dependency on VariationSearchRepositoryContract by replacing with a Wayfair wrapper
      /** @var VariationSearchRepositoryContract */
      $variationSearchRepository = pluginApp(VariationSearchRepositoryContract::class);

      // stock buffer should be the same across all pages of inventory
      $stockBuffer = $this->getNormalizedStockBuffer();

      $searchParameters = $this->getDefaultSearchParameters();

      // The Plentymarkets team instructed to start on page 1, not page 0.
      $pageNumber = 1;
      $amtOfDtosForPage = 0;

      $logKeyStart = self::LOG_KEY_START_PARTIAL;
      if ($fullInventory) {
        $logKeyStart = self::LOG_KEY_START_FULL;
      }

      $this->logger->info(TranslationHelper::getLoggerKey($logKeyStart), [
        'additionalInfo' => ['manual' => (string) $manual],
        'method' => __METHOD__
      ]);

      $startTimeStamp = $this->statusService->markInventoryStarted($fullInventory);
      $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic ") . ($fullInventory ? "Full " : "Partial ") . "inventory sync.");

      $filters = $this->getDefaultFilters();
      if (!$fullInventory) {

        $startOfWindow = $this->getStartOfDeltaSyncWindow($externalLogs);
        $syncStartAsPhpTime = strtotime($startTimeStamp);

        // look at inventory changes between last good sync and the declared sync start time.
        // (the end of this window becomes the start of the next window)
        self::applyTimeFilter($filters, $startOfWindow, $syncStartAsPhpTime);
      }

      $variationSearchRepository->setFilters($filters);

      do {

        $mostRecentFullStart = $this->statusService->getLastAttemptTime(true);

        if (
          isset($mostRecentFullStart) && !empty($mostRecentFullStart)
          && strtotime($mostRecentFullStart) > strtotime($startTimeStamp)
        ) {
          // a sync of all items is happening AFTER this one, so this sync is stale!
          throw new InventorySyncInterruptedException("Inventory sync started at " . $startTimeStamp .
            " lost priority to Full Inventory sync started at " . $mostRecentFullStart);
        }

        $mostRecentPartialStart = $this->statusService->getLastAttemptTime(false);

        if (
          !$fullInventory
          && isset($mostRecentPartialStart) && !empty($mostRecentPartialStart)
          && strtotime($mostRecentPartialStart) > strtotime($startTimeStamp)
        ) {
          // this is a partial inventory, but another partial inventory started after it.
          // this one should stop running.
          throw new InventorySyncInterruptedException("Inventory sync started at " . $startTimeStamp .
            " lost priority to Inventory sync started at " . $mostRecentPartialStart);
        }

        $unixTimeAtPageStart = TimeHelper::getMilliseconds();

        /** @var RequestDTO[] collection of DTOs to include in a bulk update*/
        $requestDTOsForPage = [];
        /** @var int[] collection of the IDs of Variations for which requestDTOs were created*/
        $variationIdsForPage = [];
        $searchParameters['page'] = (string) $pageNumber;
        $variationSearchRepository->setSearchParams($searchParameters);
        $variationSearchResponse = $variationSearchRepository->search();

        /** @var array $variationWithStock information about a single Variation, including stock for each Warehouse */
        foreach ($variationSearchResponse->getResult() as $variationWithStock) {
          $haveStockForVariation = false;
          /** @var RequestDTO[] */
          $requestDTOsForVariation = $this->inventoryMapper->createInventoryDTOsFromVariation($variationWithStock, $itemMappingMethod, $stockBuffer);

          if (count($requestDTOsForVariation)) {
            $requestDTOsForPage = array_merge($requestDTOsForPage, $requestDTOsForVariation);
            $variationIdsForPage[] = $variationWithStock['id'];
          }
        }

        $amtOfDtosForPage = count($requestDTOsForPage);

        if ($amtOfDtosForPage <= 0) {
          $this->logger
            ->debug(
              TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
              [
                'additionalInfo' => [
                  'pageNum' => $pageNumber,
                  'info' => 'No request DTOs to send'
                ],
                'method' => __METHOD__
              ]
            );

          $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': No items to update for page ' . $pageNumber);
        } else {
          $amtOfVariationsForPage = count($variationIdsForPage);
          $totalVariationIdsInDTOsForAllPages += $amtOfVariationsForPage;

          $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': ' . (string) $amtOfDtosForPage . ' updates to send for page ' . $pageNumber);

          $this->logger->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
            [
              'additionalInfo' => [
                'page' => $pageNumber,
                'amtOfDtosForPage' => $amtOfDtosForPage,
                'amtOfVariationsForPage' => $amtOfVariationsForPage
              ],
              'method' => __METHOD__
            ]
          );

          $totalTimeSpentGatheringData += TimeHelper::getMilliseconds() - $unixTimeAtPageStart;
          $unixTimeBeforeSendingData = TimeHelper::getMilliseconds();

          $totalDtosAttempted +=  $amtOfDtosForPage;

          $responseDto = $this->inventoryService->updateBulk($requestDTOsForPage, $fullInventory);

          $totalTimeSpentSendingData += TimeHelper::getMilliseconds() - $unixTimeBeforeSendingData;

          $amtErrors = count($responseDto->getErrors());
          // TODO: verify that there is a 1:1 relationship between errors and DTOs
          $totalDtosSaved += $amtOfDtosForPage - $amtErrors;
          $totalDtosFailed += $amtErrors;
        }

        $this->logger->debug(
          TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
          [
            'additionalInfo' => [
              'fullInventory' => (string) $fullInventory,
              'page_num' => (string) $pageNumber,
              'info' => 'page done',
              'resultsForPage' => $responseDto
            ],
            'method' => __METHOD__
          ]
        );

        $pageNumber++;
      } while (isset($variationSearchResponse) && !$variationSearchResponse->isLastPage());

      if ($totalDtosFailed > 0) {
        $this->statusService->markInventoryFailed($fullInventory);

        $logKeyFailed = $fullInventory ? self::LOG_KEY_FAILED_FULL : self::LOG_KEY_FAILED_PARTIAL;

        $info = ['manual' => (string) $manual];

        $this->logger->error(TranslationHelper::getLoggerKey($logKeyFailed), [
          'additionalInfo' => $info,
          'method' => __METHOD__
        ]);
      } else {
        $this->statusService->markInventoryComplete($fullInventory, $startTimeStamp);
      }
    } catch (InventorySyncBlockedException $e) {
      $logKey = $fullInventory ? self::LOG_KEY_SKIPPED_FULL : self::LOG_KEY_SKIPPED_PARTIAL;

      $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
        'additionalInfo' => [
          'manual' => (string) $manual,
        ],
        'method' => __METHOD__
      ]);

      $externalLogs->addInventoryLog('Inventory blocked: ' . $e->getMessage(), 'inventoryBlocked' . ($fullInventory ? 'Full' : ''), 1, 0, false, $e->getTraceAsString());
    } catch (InventorySyncInterruptedException $e) {

      $logKey = $fullInventory ? self::LOG_KEY_INTERRUPTED_FULL : self::LOG_KEY_INTERRUPTED_PARTIAL;

      $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
        'additionalInfo' => [
          'manual' => (string) $manual,
          'message' => $e->getMessage()
        ],
        'method' => __METHOD__
      ]);

      if ($this->statusService->getLastAttemptTime($fullInventory) == $startTimeStamp) {
        // this is the most recent sync of this flavor, and it is quitting early.
        // the inventory status service should see this flavor of sync as "idle" so that the next one can start.
        $this->statusService->resetState($fullInventory);
      }

      $externalLogs->addInventoryLog('Inventory interrupted: ' . $e->getMessage(), 'inventoryInterrupted' . ($fullInventory ? 'Full' : ''), 1, 0, false, $e->getTraceAsString());
    } catch (\Exception $e) {
      $externalLogs->addInventoryLog('Inventory exception: ' . $e->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false);

      // bulk update failed, so everything we were going to save should be considered failing.
      // (we want the failure amount to be more than zero in order for client to know this failed.)
      $totalDtosFailed += $amtOfDtosForPage;

      // statusService will log out to plentymarkets logs
      $this->statusService->markInventoryFailed($fullInventory);

      $logKeyFailed = self::LOG_KEY_FAILED_PARTIAL;
      if ($fullInventory) {
        $logKeyFailed = self::LOG_KEY_FAILED_FULL;
      }

      $info = ['manual' => (string) $manual];
      $info['exceptionType'] = get_class($e);
      $info['errorMessage'] = $e->getMessage();
      $info['stackTrace'] = $e->getTraceAsString();

      $this->logger->error(TranslationHelper::getLoggerKey($logKeyFailed), [
        'additionalInfo' => $info,
        'method' => __METHOD__
      ]);
    } finally {

      $elapsedTime = time() - strtotime($startTimeStamp);

      $infoMap = [
        'manual' => (string) $manual,
        'dtosAttempted' => $totalDtosAttempted,
        'dtosSaved' => $totalDtosSaved,
        'dtosFailed' => $totalDtosFailed,
        'elapsedTime' => $elapsedTime,
        'variationsAttempted' => $totalVariationIdsInDTOsForAllPages
      ];

      $logKeyEnd = self::LOG_KEY_END_PARTIAL;
      if ($fullInventory) {
        $logKeyEnd = self::LOG_KEY_END_FULL;
      }

      $this->logger->info(TranslationHelper::getLoggerKey($logKeyEnd), [
        'additionalInfo' => $infoMap,
        'method' => __METHOD__
      ]);

      if (isset($externalLogs) && isset($this->logSenderService)) {
        $externalLogs->addInventoryLog('Inventory syncs attempted', 'totalDtosAttempted' . ($fullInventory ? 'Full' : ''), $totalDtosAttempted, $totalTimeSpentGatheringData);
        $externalLogs->addInventoryLog('Inventory syncs completed', 'totalDtosSaved' . ($fullInventory ? 'Full' : ''), $totalDtosSaved, $totalTimeSpentSendingData);
        $externalLogs->addInventoryLog('Inventory syncs failed', 'totalDtosFailed' . ($fullInventory ? 'Full' : ''), $totalDtosFailed, $totalTimeSpentSendingData);

        $externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic ") . ($fullInventory ? "Full " : "Partial ") . "inventory sync.");

        $this->logSenderService->execute($externalLogs->getLogs());
      }
    }

    return $infoMap;
  }

  /**
   * Get the default search parameters for VariationSearchRepository
   * @return array
   */
  public function getDefaultSearchParameters()
  {
    return [
      'with' => [
        'stock' => true,
        'variationSkus' => true,
        'variationBarcodes' => true,
        'variationMarkets' => true
      ],

      'itemsPerPage' => self::VARIATIONS_PER_PAGE
    ];
  }

  /**
   * Get the base VariationSearchRepository filters based on the supplier config
   * @return array
   */
  private function getDefaultFilters(): array
  {

    $filter = [
      'isActive' => true
    ];

    if (!$this->configHelper->isAllItemsActive()) {
      $filter['referrerId'] = [$this->configHelper->getOrderReferrerValue()];
    }

    return $filter;
  }

  /**
   * Add a time-based filter to a VariationSearchRepository filter array,
   * limiting the results to those that have changed in the given time window
   *
   * @param array $filters the filter array to add on to
   * @param integer $startOfWindow php time in seconds for earliest change time
   * @param integer $endOfWindow (optional) php time in seconds for latest change time
   * @return void
   */
  private static function applyTimeFilter($filters, int $startOfWindow, int $endOfWindow = null)
  {
    if (!isset($endOfWindow)) {
      $endOfWindow = time();
    }

    if (isset($filters) && isset($startOfWindow) && $startOfWindow > 0) {
      $filters['updatedBetween'] = [
        'timestampFrom' => $startOfWindow,
        'timestampTo' => $endOfWindow,
      ];
    }
  }

  /**
   * Get the stock buffer value, normalized to 0
   *
   * @return int
   */
  private function getNormalizedStockBuffer()
  {
    $stockBuffer = null;
    $stockBuffer = $this->configHelper->getStockBufferValue();


    if (isset($stockBuffer)) {
      if ($stockBuffer >= 0) {
        return $stockBuffer;
      }

      // invalid value for buffer
      $this->logger->warning(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_STOCK_BUFFER),
        [
          'additionalInfo' => [
            'stockBuffer' => json_encode($stockBuffer)
          ],
          'method' => __METHOD__
        ]
      );
    }

    return 0;
  }

  /**
   * Check if the state of the Inventory Service has been "running" for more than the maximum allotted time
   * This functionality was extracted from the old UpdateFullInventoryStatusCron
   * @param bool $full check full inventory service instead of partial
   * @return bool
   */
  private function serviceHasBeenRunningTooLong(bool $full): bool
  {

    if (!$this->statusService->isInventoryRunning($full)) {
      return false;
    }

    $maxTime = $full ? self::MAX_INVENTORY_TIME_FULL : self::MAX_INVENTORY_TIME_PARTIAL;
    $logKey = $full ? self::LOG_KEY_LONG_RUN_FULL : self::LOG_KEY_LONG_RUN_PARTIAL;

    $lastStart = $this->statusService->getLastAttemptTime($full);
    if (isset($lastStart) && !empty($lastStart) && (time() - strtotime($lastStart)) > $maxTime) {

      $this->logger->warning(TranslationHelper::getLoggerKey($logKey), [
        'additionalInfo' => ['startedOn' => $lastStart, 'maximumTime' => $maxTime],
        'method' => __METHOD__
      ]);
      return true;
    }

    return false;
  }

  /**
   * Get the php time (in seconds) for the start of a partial sync
   *
   * @param ExternalLogs $externalLogs
   *
   * @return int
   */
  private function getStartOfDeltaSyncWindow(ExternalLogs $externalLogs = null): int
  {
    $windowStart = 0;

    $lastWindowEnd = $this->statusService->getLastSuccessfulAttemptTime(false);
    $lastGoodFullStart = $this->statusService->getLastSuccessfulAttemptTime(true);

    if (isset($lastWindowEnd) && !empty($lastWindowEnd)) {
      // new window should be directly after the previous window
      $windowStart = strtotime($lastWindowEnd);
    }

    if (isset($lastGoodFullStart) && !empty($lastGoodFullStart)) {
      $numericTimeForFullSync = strtotime($lastGoodFullStart);
      if ($windowStart > 0 && $numericTimeForFullSync > $windowStart) {
        // full sync happened more recently than partial sync - use that time.
        $windowStart = $numericTimeForFullSync;
      }
    }

    if ($windowStart > 0) {
      return $windowStart;
    }

    if (isset($externalLogs)) {
      $externalLogs->addErrorLog("Starting a partial inventory sync when no inventory syncs of any sort have been completed.");
    }

    // in case we somehow got here, use an arbitrary window so that some data gets updated
    return time() - 7200;
  }

  /**
   * Check if we can start a new sync or not
   *
   * @param boolean $fullInventory
   * @return boolean
   */
  private function syncIsBlocked(bool $fullInventory): bool
  {
    if ($this->statusService->isInventoryRunning(true) && !$this->serviceHasBeenRunningTooLong(true)) {
      // full sync is running within normal time limit - don't start anything new while it's running
      return true;
    }

    if ($fullInventory) {
      // no full inventory is running, so we can start a new one
      return false;
    }

    // don't run a partial sync on top of another partial sync that is running with in the time limit.
    return $this->statusService->isInventoryRunning($fullInventory) && !$this->serviceHasBeenRunningTooLong($fullInventory);
  }
}
