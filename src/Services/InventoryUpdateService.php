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

  // TODO: make this user-configurable in a future update
  const MAX_INVENTORY_TIME_FULL = 7200;
  const MAX_INVENTORY_TIME_PARTIAL = self::MAX_INVENTORY_TIME_FULL;

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
   * Validate a request for inventory update
   * @param RequestDTO $inventoryRequestDTO the DTO to validate
   * @return bool
   */
  private function validateInventoryRequestData($inventoryRequestDTO): bool
  {
    if (!isset($inventoryRequestDTO)) {
      return false;
    }

    $issues = [];

    $supplierId = $inventoryRequestDTO->getSupplierId();

    if (!isset($supplierId) || $supplierId <= 0) {
      $issues[] = "Supplier ID is missing or invalid";
    }

    $partNum = $inventoryRequestDTO->getSupplierPartNumber();

    if (!isset($partNum) || empty($partNum)) {
      $issues[] = "Supplier Part number is missing";
    }

    $onHand = $inventoryRequestDTO->getQuantityOnHand();

    if (isset($onHand)) {
      if ($onHand  < -1) {
        $issues[] = "Quantity on Hand is less than negative one";
      }
    } else {
      $issues[] = "Quantity On Hand is missing";
    }

    if (!isset($issues) || empty($issues)) {
      return true;
    }

    // TODO: replace issues with translated messages?
    $this->logger
      ->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_INVENTORY_DTO),
        [
          'additionalInfo' => [
            'message' => 'inventory request data is invalid',
            'issues' => json_encode($issues),
            'data' => $inventoryRequestDTO->toArray(),
          ],
          'method' => __METHOD__
        ]
      );

    return false;
  }

  /**
   * Send inventory updates from Plentymarkets to Wayfair.
   * The optional start parameter filters the update set down to items changed since the provided timestamp
   *
   * @param bool $fullInventory flag for syncing entire inventory versus partial inventory update
   * @param bool $manual flag used during logging which indicates that the sync was manually induced
   *
   * @return array
   */
  public function sync(bool $fullInventory, bool $manual = false): array
  {
    $mostRecentFullStart = null;
    $lastStartTime = null;
    $timeStart = null;

    $totalDtosAttempted = 0;
    $totalDtosSaved = 0;
    $totalDtosFailed = 0;
    $totalTimeSpentGatheringData = 0;
    $totalTimeSpentSendingData = 0;
    $totalTimeSyncingAllPages = 0;

    $variationIdsInDTOs = [];

    // TODO: add more aggregate counters, etc.

    /** @var ExternalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    try {

      if (!$fullInventory) {
        // if no full sync attempt is on record, we should do a full sync in place of a differential sync.
        $mostRecentFullStart = $this->statusService->getLastAttemptTime(true);
        if (!isset($mostRecentFullStart) || empty($mostRecentFullStart)) {

          $this->logger->info(
            TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SYNCS),
            [
              'additionalInfo' => [],
              'method' => __METHOD__
            ]
          );

          // NOTICE: due to recursion, this log will show up AFTER the logs for the Full Sync!
          $externalLogs->addInfoLog("Forced a full inventory sync as there are no syncs on record");

          return $this->sync(true, $manual);
        }
      }

      $lastStartTime = $this->statusService->getLastAttemptTime($fullInventory);

      $fullSyncBlocked = $this->statusService->isInventoryRunning(true) && !$this->serviceHasBeenRunningTooLong(true);
      // don't run a partial sync while a full sync is running
      $currentSyncBlocked = $fullSyncBlocked || $this->statusService->isInventoryRunning($fullInventory) && !$this->serviceHasBeenRunningTooLong($fullInventory);

      // potential race conditions - change service management strategy in a future update
      // (but this is better than letting the old UpdateFullInventoryStatusCron randomly change service states)
      if ($currentSyncBlocked) {
        throw new InventorySyncBlockedException();
      }

      $filters = $this->getDefaultFilters();
      if (!$fullInventory) {
        self::applyTimeFilter($filters, $lastStartTime);
      }

      // look up item mapping method at this level to ensure consistency and improve efficiency
      $itemMappingMethod = $this->configHelper->getItemMappingMethod();

      // TODO: remove dependency on VariationSearchRepositoryContract by replacing with a Wayfair wrapper
      /** @var VariationSearchRepositoryContract */
      $variationSearchRepository = pluginApp(VariationSearchRepositoryContract::class);

      $variationSearchRepository->setFilters($filters);

      // stock buffer should be the same across all pages of inventory
      $stockBuffer = $this->getNormalizedStockBuffer();

      $searchParameters = $this->getDefaultSearchParameters();

      $page = 0;
      $amtOfDtosForPage = 0;

      $logKeyStart = self::LOG_KEY_START_PARTIAL;
      if ($fullInventory) {
        $logKeyStart = self::LOG_KEY_START_FULL;
      }

      $this->logger->info(TranslationHelper::getLoggerKey($logKeyStart), [
        'additionalInfo' => ['manual' => (string) $manual],
        'method' => __METHOD__
      ]);

      $timeStart = $this->statusService->markInventoryStarted($fullInventory, $manual);
      $externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic ") . ($fullInventory ? "Full " : "Partial ") . "inventory sync.");

      try {
        do {

          $mostRecentFullStart = $this->statusService->getLastAttemptTime($fullInventory);
          if (strtotime($mostRecentFullStart) > strtotime($timeStart)) {
            throw new InventorySyncInterruptedException("Inventory sync started at " . $timeStart .
              " lost priority to Full Inventory sync started at " . $mostRecentFullStart);
          }

          $unixTimeAtPageStart = TimeHelper::getMilliseconds();

          /** @var RequestDTO[] $validatedRequestDTOs collection of DTOs to include in a bulk update*/
          $validatedRequestDTOs = [];
          $searchParameters['page'] = (string) $page;
          $variationSearchRepository->setSearchParams($searchParameters);
          $response = $variationSearchRepository->search();

          /** @var array $variationWithStock information about a single Variation, including stock for each Warehouse */
          foreach ($response->getResult() as $variationWithStock) {
            $haveStockForVariation = false;
            /** @var RequestDTO[] non-normalized candidates for inclusion in bulk update */
            $rawInventoryRequestDTOsForVariation = $this->inventoryMapper->createInventoryDTOsFromVariation($variationWithStock, $itemMappingMethod, $stockBuffer);
            foreach ($rawInventoryRequestDTOsForVariation as $dto) {
              // validation method will output logs on failure
              if ($this->validateInventoryRequestData($dto)) {
                $validatedRequestDTOs[] = $dto;
                $haveStockForVariation = true;
              }
            }

            if ($haveStockForVariation)
            {
              $variationIdsInDTOs[] = $variationWithStock['id'];
            }

          }

          $amtOfDtosForPage = count($validatedRequestDTOs);

          if ($amtOfDtosForPage <= 0) {
            $this->logger
              ->debug(
                TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
                [
                  'additionalInfo' => ['info' => 'No items to update'],
                  'method' => __METHOD__
                ]
              );

            $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': No items to update for page ' . $page);
          } else {
            $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': ' . (string) $amtOfDtosForPage . ' updates to send for page ' . $page);

            $this->logger->debug(
              TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
              [
                'additionalInfo' => ['info' => (string) $amtOfDtosForPage . ' updates to send', 'page' => $page],
                'method' => __METHOD__
              ]
            );

            $totalTimeSpentGatheringData += TimeHelper::getMilliseconds() - $unixTimeAtPageStart;
            $unixTimeBeforeSendingData = TimeHelper::getMilliseconds();

            $totalDtosAttempted +=  $amtOfDtosForPage;

            $responseDto = $this->inventoryService->updateBulk($validatedRequestDTOs, $fullInventory);

            $totalTimeSpentSendingData += TimeHelper::getMilliseconds() - $unixTimeBeforeSendingData;

            $amtErrors = count($responseDto->getErrors());
            // TODO: verify that there can only be one
            $totalDtosSaved += $amtOfDtosForPage - $amtErrors;
            $totalDtosFailed += $amtErrors;
          }

          $this->logger->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
            [
              'additionalInfo' => [
                'fullInventory' => (string) $fullInventory,
                'page_num' => (string) $page,
                'info' => 'page done',
                'resultsForPage' => $dto
              ],
              'method' => __METHOD__
            ]
          );

          $page++;
        } while (!$response->isLastPage());

        $totalTimeSyncingAllPages = TimeHelper::getMilliseconds() - strtotime($timeStart);

        if ($totalDtosFailed > 0) {
          $this->statusService->markInventoryFailed($fullInventory, $manual);

          $logKeyFailed = self::LOG_KEY_FAILED_PARTIAL;
          if ($fullInventory) {
            $logKeyFailed = self::LOG_KEY_FAILED_FULL;
          }

          $info = ['manual' => (string) $manual];
          if (isset($exception)) {
            $info['exceptionType'] = get_class($exception);
            $info['errorMessage'] = $exception->getMessage();
            $info['stackTrace'] = $exception->getTraceAsString();
          }

          $this->logger->error(TranslationHelper::getLoggerKey($logKeyFailed), [
            'additionalInfo' => $info,
            'method' => __METHOD__
          ]);
        } else {
          $this->statusService->markInventoryComplete($fullInventory, $manual);
        }
      } catch (InventorySyncBlockedException $e) {
        $stateArray = $this->statusService->getServiceState($fullInventory);

        $logKey = $fullInventory ? self::LOG_KEY_SKIPPED_FULL : self::LOG_KEY_SKIPPED_PARTIAL;

        $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
          'additionalInfo' => ['manual' => (string) $manual, 'otherSyncStartedAt' => $lastStartTime, 'state' => $stateArray],
          'method' => __METHOD__
        ]);
        $externalLogs->addWarningLog(($manual ? "Manual " : "Automatic ") . ($fullInventory ? "Full " : "Partial ") . "Inventory sync BLOCKED - already running");
      } catch (InventorySyncInterruptedException $e) {
        $externalLogs->addInventoryLog('Inventory: ' . $e->getMessage(), 'inventoryInterrupted' . ($fullInventory ? 'Full' : ''), 1, 0, false);

        $logKey = $fullInventory ? self::LOG_KEY_INTERRUPTED_FULL : self::LOG_KEY_INTERRUPTED_PARTIAL;

        $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
          'additionalInfo' => ['manual' => (string) $manual, 'mostRecentFullStart' => $mostRecentFullStart],
          'method' => __METHOD__
        ]);

        if (strcasecmp($this->statusService->getLastAttemptTime($fullInventory), $timeStart) == 0) {
          // this is the most recent sync of this flavor, and it is quitting early.
          // the inventory status service should see this flavor of sync as "idle" so that the next one can start.
          $this->statusService->resetState($fullInventory);
        }
      } catch (\Exception $e) {
        $externalLogs->addInventoryLog('Inventory: ' . $e->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false);

        // bulk update failed, so everything we were going to save should be considered failing.
        // (we want the failure amount to be more than zero in order for client to know this failed.)
        $totalDtosFailed += $amtOfDtosForPage;

        // statusService will log out to plentymarkets logs
        $this->statusService->markInventoryFailed(true, $manual, $e);
      } finally {

        $logKeyEnd = self::LOG_KEY_END_PARTIAL;
        if ($fullInventory) {
          $logKeyEnd = self::LOG_KEY_END_FULL;
        }

        $this->logger->info(TranslationHelper::getLoggerKey($logKeyEnd), [
          'manual' => (string) $manual, 'method' => __METHOD__
        ]);

        if (isset($externalLogs)) {
          $externalLogs->addInventoryLog('Inventory syncs attempted', 'totalDtosAttempted' . ($fullInventory ? 'Full' : ''), $totalDtosAttempted, $totalTimeSpentGatheringData);
          $externalLogs->addInventoryLog('Inventory syncs completed', 'totalDtosSaved' . ($fullInventory ? 'Full' : ''), $totalDtosSaved, $totalTimeSpentSendingData);
          $externalLogs->addInventoryLog('Inventory syncs failed', 'totalDtosFailed' . ($fullInventory ? 'Full' : ''), $totalDtosFailed, $totalTimeSpentSendingData);

          $externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic ") . ($fullInventory ? "Full " : "Partial ") . "inventory sync.");
        }
      }

      return [
        'dtosAttempted' => $totalDtosAttempted,
        'dtosSaved' => $totalDtosSaved,
        'dtosFailed' => $totalDtosFailed,
        'elapsedTime' => $totalTimeSyncingAllPages
      ];
    } finally {
      if (isset($this->logSenderService) && isset($externalLogs) && null !== $externalLogs->getLogs() && count($externalLogs->getLogs())) {
        $this->logSenderService->execute($externalLogs->getLogs());
      }
    }
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
   * Add a time-based filter to a VariationSearchRepository filter array
   * The time starts based on the argument,
   *
   * @param array $filters the filter array to add on to
   * @param integer $startOfWindow when the time starts
   * @return void
   */
  private static function applyTimeFilter($filters, int $startOfWindow)
  {
    if (isset($filters) && isset($startOfWindow) && $startOfWindow > 0) {
      $filters['updatedBetween'] = [
        'timestampFrom' => $startOfWindow,
        'timestampTo' => time(),
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
          'additionalInfo' => ['stockBuffer' => $stockBuffer],
          'method' => __METHOD__
        ]
      );
    }

    return 0;
  }

  /**
   * Check if the state of the Inventory Service has been "running" for more than the maximum allotted time
   * This functionality was extracted from the old UpdateFullInventoryStatusCron
   * @param boolean $full check full inventory service instead of partial
   * @return bool
   */
  private function serviceHasBeenRunningTooLong(bool $full): bool
  {
    $maxTime = self::MAX_INVENTORY_TIME_PARTIAL;
    $logKey = self::LOG_KEY_LONG_RUN_PARTIAL;
    if ($full) {
      $maxTime = self::MAX_INVENTORY_TIME_FULL;
      $logKey = self::LOG_KEY_LONG_RUN_FULL;
    }

    if ($this->statusService->isInventoryRunning($full)) {
      $lastStateChange = $this->statusService->getStateChangeTime(true);
      if (!$lastStateChange || (\time() - \strtotime($lastStateChange)) > $maxTime) {

        $this->logger->warning(TranslationHelper::getLoggerKey($logKey), [
          'additionalInfo' => ['lastStateChange' => $lastStateChange, 'maximumTime' => $maxTime],
          'method' => __METHOD__
        ]);
        return true;
      }
    }

    return false;
  }
}
