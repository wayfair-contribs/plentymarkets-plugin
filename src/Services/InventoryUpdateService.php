<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use DateTime;
use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\Core\Api\Services\InventoryService;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\Inventory\RequestDTO;
use Wayfair\Core\Exceptions\FullInventorySyncInProgressException;
use Wayfair\Core\Exceptions\InventoryException;
use Wayfair\Core\Exceptions\InventorySyncBlockedException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Core\Exceptions\WayfairVariationsMissingException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Core\Helpers\TimeHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Models\InventoryUpdateResult;

/**
 * Service module for sending inventory updates to Wayfair
 */
class InventoryUpdateService
{
  const LOG_KEY_DEBUG = 'debugInventoryUpdate';
  const LOG_KEY_INVALID_INVENTORY_DTO = 'invalidInventoryDto';
  const LOG_KEY_INVALID_STOCK_BUFFER = 'invalidStockBufferValue';
  const LOG_KEY_NO_SYNCS = 'noInventorySyncs';
  const LOG_KEY_LONG_RUN = 'inventoryLongRunning';
  const LOG_KEY_INTERRUPTED = 'inventoryInterrupted';
  const LOG_KEY_START = 'inventoryStart';
  const LOG_KEY_END = 'inventoryEnd';
  const LOG_KEY_FAILED = 'inventoryFailed';
  const LOG_KEY_BLOCKED = 'inventoryBlocked';
  const LOG_KEY_NO_VARIATIONS = 'inventoryNoVariations';
  const LOG_KEY_NO_STOCKS = 'inventoryNoStocks';
  const LOG_KEY_INVENTORY_ERRORS = 'inventoryErrors';

  // TODO: make these user-configurable in a future update
  const MAX_INVENTORY_TIME = 14400;

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
   *
   * @return InventoryUpdateResult
   * @throws InventoryException
   */
  public function sync(bool $fullInventory): InventoryUpdateResult
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
      if ($this->statusService->isInventoryRunning()) {
        $olderSyncStartedAt = $this->statusService->getStartOfMostRecentAttempt();
        $olderSyncIsForAllItems = $this->statusService->isInventoryRunning(true);

        if (!$fullInventory && isset($olderSyncStartedAt) && !empty($olderSyncStartedAt) && time() - strtotime($olderSyncStartedAt) < self::MAX_INVENTORY_TIME) {
          // other inventory sync is running within allowed time limits

          $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_BLOCKED), [
            'additionalInfo' => [
              'full' => (string) $fullInventory
            ],
            'method' => __METHOD__
          ]);

          $externalLogs->addInventoryLog('Inventory blocked', 'inventoryBlocked' . ($fullInventory ? 'Full' : ''), 1, 0, false);

          if ($olderSyncIsForAllItems) {
            throw new FullInventorySyncInProgressException("A Full Inventory Sync is in progress, preventing this one from starting.");
          }

          throw new InventorySyncBlockedException("A regular inventory sync is in progress, preventing this one from starting");
        }

        // other inventory sync is stale or stalled, so a new one should start.
        $this->logger->warning(TranslationHelper::getLoggerKey(self::LOG_KEY_LONG_RUN), [
          'additionalInfo' => [
            'olderSyncStartedAt' => $olderSyncStartedAt,
            'olderSyncIsForAllItems' => $olderSyncIsForAllItems,
            'maximumTime' => self::MAX_INVENTORY_TIME
          ],
          'method' => __METHOD__
        ]);

        $externalLogs->addErrorLog('Inventory ' . ($fullInventory ? 'Full' : '') . ' overriding a currently running inventory sync process that started at ' . $olderSyncStartedAt);
      }
    } catch (\Exception $e) {
      $externalLogs->addInventoryLog('Inventory status check caused exception: ' . $e->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false, $e->getTraceAsString());

      $info = [
        'full' => (string) $fullInventory,
        'exceptionType' => get_class($e),
        'errorMessage' => $e->getMessage(),
        'stackTrace' => $e->getTraceAsString()
      ];

      $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_FAILED), [
        'additionalInfo' => $info,
        'method' => __METHOD__
      ]);

      throw new InventoryException($e->getMessage());
    }

    try {
      // look up item mapping method at this level to ensure consistency and efficiency
      $itemMappingMethod = $this->configHelper->getItemMappingMethod();

      // look up referrer ID at this level to ensure consistency and efficiency
      $referrerId = $this->configHelper->getOrderReferrerValue();

      /** @var VariationSearchRepositoryContract */
      $variationSearchRepository = pluginApp(VariationSearchRepositoryContract::class);

      // stock buffer should be the same across all pages of inventory
      $stockBuffer = $this->getNormalizedStockBuffer();

      $searchParameters = $this->getDefaultSearchParameters();

      // The Plentymarkets team instructed to start on page 1, not page 0.
      $pageNumber = 1;
      $amtOfDtosForPage = 0;

      $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_START), [
        'additionalInfo' => [
          'full' => (string) $fullInventory
        ],
        'method' => __METHOD__
      ]);

      $startTimeStamp = $this->statusService->markInventoryStarted($fullInventory);
      $externalLogs->addInfoLog("Starting " . ($fullInventory ? "Full " : "Partial ") . "inventory sync.");

      $variationSearchRepository->setFilters($this->getDefaultFilters());

      $windowStart = null;
      $windowEnd = null;

      if (!$fullInventory) {
        $windowStart = $this->getStartOfDeltaSyncWindow($externalLogs);
        $windowEnd = (date_create($startTimeStamp))->format(DateTime::W3C);
      }

      do {
        $startTimeInDatabase = $this->statusService->getStartOfMostRecentAttempt();

        if (isset($startTimeInDatabase) && !empty($startTimeInDatabase) && strtotime($startTimeInDatabase) > strtotime($startTimeStamp)) {
          throw new InventorySyncInterruptedException("Inventory sync started at " . $startTimeStamp .
            " is quitting due to staleness. A new inventory sync started at " . $startTimeInDatabase);
        }

        $unixTimeAtPageStart = TimeHelper::getMilliseconds();

        /** @var RequestDTO[] collection of DTOs to include in a bulk update*/
        $requestDTOsForPage = [];
        /** @var int[] collection of the IDs of Variations for which requestDTOs were created*/
        $variationIdsForPage = [];
        $searchParameters['page'] = (string) $pageNumber;
        $variationSearchRepository->setSearchParams($searchParameters);
        $this->logger
          ->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
            [
              'additionalInfo' => [
                'full' => (string) $fullInventory,
                'pageNum' => $pageNumber,
                'info' => 'Searching Variation repo',
                'searchFilters' => $variationSearchRepository->getFilters(),
                'searchConditions' => $variationSearchRepository->getConditions(),
                'windowStart' => json_encode($windowStart),
                'windowEnd' => json_encode($windowEnd)
              ],
              'method' => __METHOD__
            ]
          );
        $variationSearchResponse = $variationSearchRepository->search();

        $searchResults = $variationSearchResponse->getResult();
        if ($pageNumber == 1 && !isset($searchResults) || empty($searchResults)) {
          $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_NO_VARIATIONS), [
            'additionalInfo' => [
              'full' => (string) $fullInventory,
              'message' => $e->getMessage()
            ],
            'method' => __METHOD__
          ]);

          $externalLogs->addInventoryLog('Inventory : no Wayfair variations', 'inventoryNoVariations' . ($fullInventory ? 'Full' : ''), 1, 0, false, $e->getTraceAsString());

          throw new WayfairVariationsMissingException("No Variations are currently linked to Wayfair");
        }

        /** @var array $variationData information about a single Variation */
        foreach ($searchResults as $variationData) {
          $haveStockForVariation = false;
          /** @var RequestDTO[] */
          $requestDTOsForVariation = $this->inventoryMapper->createInventoryDTOsFromVariation($variationData, $itemMappingMethod, $referrerId, $stockBuffer, $windowStart, $windowEnd);

          if (count($requestDTOsForVariation)) {
            $requestDTOsForPage = array_merge($requestDTOsForPage, $requestDTOsForVariation);
            $variationIdsForPage[] = $variationData['id'];
          }
        }

        $amtOfDtosForPage = count($requestDTOsForPage);

        if ($amtOfDtosForPage > 0) {
          $amtOfVariationsForPage = count($variationIdsForPage);
          $totalVariationIdsInDTOsForAllPages += $amtOfVariationsForPage;

          $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': ' . (string) $amtOfDtosForPage . ' updates to send for page ' . $pageNumber);

          $this->logger->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
            [
              'additionalInfo' => [
                'full' => (string) $fullInventory,
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

          $amtErrors = 0;
          $errors = $responseDto->getErrors();

          if (isset($errors) && !empty($errors)) {
            $amtErrors = count($errors);

            $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_ERRORS), [
              'additionalInfo' => [
                'full' => (string) $fullInventory,
                'errors' => json_encode($errors)
              ],
              'method' => __METHOD__
            ]);

            $externalLogs->addErrorLog('Inventory ' . ($fullInventory ? 'Full' : '') . ' errors found for page ' . $pageNumber, json_encode($errors));
          }

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

      $this->statusService->markInventoryIdle();

      $info = ['full' => (string) $fullInventory];

      if ($fullInventory && $totalDtosAttempted < 1) {
        $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_NO_STOCKS), [
          'additionalInfo' => $info,
          'method' => __METHOD__
        ]);
      } elseif ($totalDtosFailed > 0) {
        $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_FAILED), [
          'additionalInfo' => $info,
          'method' => __METHOD__
        ]);
      } else {
        $this->statusService->markInventoryComplete($fullInventory, $startTimeStamp, $totalDtosSaved);
      }
    } catch (InventorySyncInterruptedException $e) {

      $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_INTERRUPTED), [
        'additionalInfo' => [
          'full' => (string) $fullInventory,
          'message' => $e->getMessage()
        ],
        'method' => __METHOD__
      ]);

      $externalLogs->addInventoryLog('Inventory interrupted: ' . $e->getMessage(), 'inventoryInterrupted' . ($fullInventory ? 'Full' : ''), 1, 0, false, $e->getTraceAsString());

      // re-throw so that we don't update the inventory status - it will conflict with the run that interrupted this one!
      throw $e;
    } catch (\Exception $e) {

      $this->statusService->markInventoryIdle();

      $externalLogs->addInventoryLog('Inventory exception: ' . $e->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false, $e->getTraceAsString());

      // bulk update failed, so everything we were going to save should be considered failing.
      // (we want the failure amount to be more than zero in order for client to know this failed.)
      $totalDtosFailed += $amtOfDtosForPage;

      $info = [
        'full' => (string) $fullInventory,
        'exceptionType' => get_class($e),
        'errorMessage' => $e->getMessage(),
        'stackTrace' => $e->getTraceAsString()
      ];

      $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_FAILED), [
        'additionalInfo' => $info,
        'method' => __METHOD__
      ]);

      throw new InventoryException($e->getMessage());
    } finally {

      $elapsedTime = time() - strtotime($startTimeStamp);

      /** @var InventoryUpdateResult */
      $resultObject = pluginApp(InventoryUpdateResult::class);

      $resultObject->full =  $fullInventory;
      $resultObject->dtosAttempted = $totalDtosAttempted;
      $resultObject->dtosAttempted = $totalDtosAttempted;
      $resultObject->dtosSaved = $totalDtosSaved;
      $resultObject->dtosFailed = $totalDtosFailed;
      $resultObject->elapsedTime = $elapsedTime;
      $resultObject->variationsAttempted = $totalVariationIdsInDTOsForAllPages;

      $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_END), [
        'additionalInfo' => [
          'results' => $resultObject
        ],
        'method' => __METHOD__
      ]);

      if (isset($externalLogs) && isset($this->logSenderService)) {
        $externalLogs->addInventoryLog('Inventory syncs attempted', 'totalDtosAttempted' . ($fullInventory ? 'Full' : ''), $totalDtosAttempted, $totalTimeSpentGatheringData);
        $externalLogs->addInventoryLog('Inventory syncs completed', 'totalDtosSaved' . ($fullInventory ? 'Full' : ''), $totalDtosSaved, $totalTimeSpentSendingData);
        $externalLogs->addInventoryLog('Inventory syncs failed', 'totalDtosFailed' . ($fullInventory ? 'Full' : ''), $totalDtosFailed, $totalTimeSpentSendingData);

        $externalLogs->addInfoLog("Finished " . ($fullInventory ? "Full " : "Partial ") . "inventory sync.");

        $this->logSenderService->execute($externalLogs->getLogs());
      }
    }

    return $resultObject;
  }

  /**
   * Get the default search parameters for VariationSearchRepository
   * @return array
   */
  public function getDefaultSearchParameters()
  {
    return [
      'with' => [
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
   * Get the W3C-formatted time for the start of a partial sync
   *
   * @param ExternalLogs $externalLogs
   *
   * @return string
   */
  private function getStartOfDeltaSyncWindow(ExternalLogs $externalLogs = null): string
  {
    $windowStart = 0;

    $lastGoodPartialStart = $this->statusService->getLastCompletionStart(false);
    $lastGoodFullStart = $this->statusService->getLastCompletionStart(true);

    if (isset($lastGoodPartialStart) && !empty($lastGoodPartialStart)) {
      // new window should be directly after the previous window
      $windowStart = strtotime($lastGoodPartialStart);
    }

    if (isset($lastGoodFullStart) && !empty($lastGoodFullStart)) {
      $numericTimeForFullSync = strtotime($lastGoodFullStart);
      if ($windowStart <= 0 || $numericTimeForFullSync > $windowStart) {
        // no partial sync yet,
        // or full sync happened more recently than partial sync - use that time.
        $windowStart = $numericTimeForFullSync;
      }
    }

    if ($windowStart <= 0) {
      if (isset($externalLogs)) {
        $externalLogs->addErrorLog("Starting a partial inventory sync when no inventory syncs of any sort have been completed.");
      }

      // in case we somehow got here, use an arbitrary window so that some data gets updated
      $windowStart = time() - 7200;
    }

    // date_create does NOT accept epoch time as an integer.
    // cannot use 'new' operator in Plenty.
    return date_create("@$windowStart")->format(DateTime::W3C);
  }
}
