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
use Wayfair\Core\Exceptions\AuthException;
use Wayfair\Core\Exceptions\InventoryException;
use Wayfair\Core\Exceptions\InventorySyncInProgressException;
use Wayfair\Core\Exceptions\InventorySyncInterruptedException;
use Wayfair\Core\Exceptions\NoReferencePointForPartialInventorySyncException;
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
   * @throws AuthException
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

    $windowStart = null;
    $windowEnd = null;

    $externalLogs = pluginApp(ExternalLogs::class);

    try {

      $runStateInDatabase = $this->statusService->getServiceStatusValue();

      if ($runStateInDatabase == InventoryStatusService::FULL || ($runStateInDatabase == InventoryStatusService::PARTIAL)) {
        if (!$this->statusService->hasGoneOverTimeLimit() && !($fullInventory && $runStateInDatabase == InventoryStatusService::PARTIAL)) {
          $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_BLOCKED), [
            'additionalInfo' => [
              'full' => (string) $fullInventory
            ],
            'method' => __METHOD__
          ]);

          $externalLogs->addInventoryLog('Inventory blocked', 'inventoryBlocked' . ($fullInventory ? 'Full' : ''), 1, 0, false);

          throw new InventorySyncInProgressException("An Inventory Sync is in progress, preventing this one from starting.");
        }

        // other inventory sync is stale or stalled, so a new one should start.
        $this->logger->warning(TranslationHelper::getLoggerKey(self::LOG_KEY_LONG_RUN), [
          'additionalInfo' => [
            'newerSyncIsFullInventory' => $fullInventory,
            'olderSyncType' => $runStateInDatabase,
          ],
          'method' => __METHOD__
        ]);

        $externalLogs->addErrorLog('Inventory ' . ($fullInventory ? 'Full' : '') . ' overriding a currently running inventory sync process.');
      }

      if (!$fullInventory) {
        // check window now before changing service state to "started"
        $windowStart = $this->getStartOfDeltaSyncWindow();
      }
    } catch (AuthException $ae) {
      // re-throw Auth Exception so it can be dealt with
      throw $ae;
    } catch (InventoryException $ie) {
      // re-throw exceptions that were purposely thrown
      throw $ie;
    } catch (\Exception $e) {
      // unexpected exception that was not thrown on purpose - DB issues, etc.
      $externalLogs->addErrorLog('Inventory status checks caused exception: ' . $e->getMessage(), $e->getTraceAsString());

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

      $variationSearchRepository = pluginApp(VariationSearchRepositoryContract::class);

      // stock buffer should be the same across all pages of inventory
      $stockBuffer = $this->getNormalizedStockBuffer();

      $searchParameters = $this->getDefaultSearchParameters();

      // The Plentymarkets team instructed to start on page 1, not page 0.
      $pageNumber = 1;


      $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_START), [
        'additionalInfo' => [
          'full' => (string) $fullInventory
        ],
        'method' => __METHOD__
      ]);

      $startTimeStamp = $this->statusService->markInventoryStarted($fullInventory);
      if (!$fullInventory) {
        // end time of Inventory change filter is the moment the sync begins
        // this provides redundancy in case this sync fails!
        $windowEnd = $this->getEndOfDeltaSyncWindow($startTimeStamp);
      }

      $externalLogs->addInfoLog("Starting " . ($fullInventory ? "Full " : "Partial ") . "inventory sync.");

      $variationSearchRepository->setFilters($this->getDefaultFilters());

      do {
        $amtErrors = 0;
        $amtOfDtosForPage = 0;
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
          // let the user know why syncs are not doing anything
          $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_NO_VARIATIONS), [
            'additionalInfo' => [
              'full' => (string) $fullInventory
            ],
            'method' => __METHOD__
          ]);

          // let Wayfair know that no Inventory DTOs are expected due to lack of Wayfair Variations
          $externalLogs->addErrorLog('Inventory : no Wayfair Variations');

          // let this sync be marked as "successful" with zeroes for every piece of data
          break;
        }

        /** @var array $variationData information about a single Variation */
        foreach ($searchResults as $variationData) {
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

          $responseDto = $this->inventoryService->updateBulk($requestDTOsForPage);

          $totalTimeSpentSendingData += TimeHelper::getMilliseconds() - $unixTimeBeforeSendingData;

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
              'numSavedForPage' => $amtOfDtosForPage - $amtErrors,
              'numErrorsForPage' => $amtErrors
            ],
            'method' => __METHOD__
          ]
        );

        $pageNumber++;
      } while (isset($variationSearchResponse) && !$variationSearchResponse->isLastPage());
      // TODO: consider introducing page limits to avoid letting the job run for too long?

      // make sure not to let this get called while another sync is running!
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
      // DO NOT change inventory status as it will conflict with the sync that interrupted this one!

      $this->logger->info(TranslationHelper::getLoggerKey(self::LOG_KEY_INTERRUPTED), [
        'additionalInfo' => [
          'full' => (string) $fullInventory,
          'message' => $e->getMessage()
        ],
        'method' => __METHOD__
      ]);

      $externalLogs->addInventoryLog('Inventory interrupted: ' . $e->getMessage(), 'inventoryInterrupted' . ($fullInventory ? 'Full' : ''), 1, 0, false, $e->getTraceAsString());

      // re-throw so that caller can decide what to do about it
      throw $e;
    } catch (AuthException $e) {
      $this->statusService->markInventoryIdle();

      // bulk update failed, so everything we were going to save should be considered failing.
      // (we want the failure amount to be more than zero in order for client to know this failed.)
      $totalDtosFailed += $amtOfDtosForPage;

      $this->logFatalException($e, $fullInventory, $externalLogs);

      // let caller report auth issue
      throw $e;
    } catch (\Exception $e) {
      $this->statusService->markInventoryIdle();

      // bulk update failed, so everything we were going to save should be considered failing.
      // (we want the failure amount to be more than zero in order for client to know this failed.)
      $totalDtosFailed += $amtOfDtosForPage;

      $this->logFatalException($e, $fullInventory, $externalLogs);

      // wrap in InventoryException so that it can be handled as such
      throw new InventoryException($e->getMessage() . ' at ' . $e->getTraceAsString());
    } finally {

      $elapsedTime = time() - strtotime($startTimeStamp);

      $resultObject = pluginApp(InventoryUpdateResult::class);

      $resultObject->setFullInventory($fullInventory);
      $resultObject->setDtosAttempted($totalDtosAttempted);
      $resultObject->setDtosAttempted($totalDtosAttempted);
      $resultObject->setDtosSaved($totalDtosSaved);
      $resultObject->setDtosFailed($totalDtosFailed);
      $resultObject->setElapsedTime($elapsedTime);
      $resultObject->setVariationsAttempted($totalVariationIdsInDTOsForAllPages);

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

  private function logFatalException(\Exception $exception, bool $fullInventory, ExternalLogs $externalLogs)
  {
    $this->statusService->markInventoryIdle();

    $externalLogs->addInventoryLog('Inventory exception: ' . $exception->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false, $exception->getTraceAsString());

    $info = [
      'full' => (string) $fullInventory,
      'exceptionType' => get_class($exception),
      'errorMessage' => $e->getMessage(),
      'stackTrace' => $e->getTraceAsString()
    ];

    $this->logger->error(TranslationHelper::getLoggerKey(self::LOG_KEY_FAILED), [
      'additionalInfo' => $info,
      'method' => __METHOD__
    ]);
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
   * Wrapper around calculateStartOfDeltaSyncWindow
   * Exists so that test frameworks can track calls
   *
   * @return string
   * @throws NoReferencePointForPartialInventorySyncException
   */
  function getStartOfDeltaSyncWindow(): string
  {
    return self::calculateStartOfDeltaSyncWindow($this->statusService);
  }

  /**
   * Get the W3C-formatted time for the start of a partial sync.
   * Internal logic for getStartOfDeltaSyncWindow
   *
   * Static so that unit tests can call it easily.
   *
   * @param InventoryStatusService $statusService
   *
   * @return string
   * @throws NoReferencePointForPartialInventorySyncException
   */
  static function calculateStartOfDeltaSyncWindow(InventoryStatusService $statusService): string
  {
    $windowStart = 0;

    $lastGoodFullStart = $statusService->getLastCompletionStart(true);

    if (isset($lastGoodFullStart) && !empty($lastGoodFullStart)) {
      // default to syncing what happened after last full sync
      $windowStart = strtotime($lastGoodFullStart);
    }

    if ($windowStart <= 0) {
      throw new NoReferencePointForPartialInventorySyncException("Cannot start a partial sync before a full sync");
    }

    $lastGoodPartialStart = $statusService->getLastCompletionStart(false);

    if (isset($lastGoodPartialStart) && !empty($lastGoodPartialStart)) {
      $numericTimeForPartialSync = strtotime($lastGoodPartialStart);
      if ($numericTimeForPartialSync > $windowStart) {
        $windowStart = $numericTimeForPartialSync;
      }
    }

    // date_create does NOT accept epoch time as an integer.
    // cannot use 'new' operator in Plenty.
    return date_create("@$windowStart")->format(DateTime::W3C);
  }

  /**
   * Wrapper around formatting a timestamp,
   * in its own method in order to detect calls to it during unit tests.
   *
   * @return string
   */
  function getEndOfDeltaSyncWindow($startTimeStamp): string
  {
    return (date_create($startTimeStamp))->format(DateTime::W3C);
  }
}
