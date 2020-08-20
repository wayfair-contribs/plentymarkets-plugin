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
  const LOG_KEY_INVENTORY_UPDATE_END = 'inventoryUpdateEnd';
  const LOG_KEY_INVENTORY_UPDATE_ERROR = 'inventoryUpdateError';
  const LOG_KEY_INVENTORY_UPDATE_START = 'inventoryUpdateStart';
  const LOG_KEY_INVALID_INVENTORY_DTO = 'invalidInventoryDto';
  const LOG_KEY_INVALID_STOCK_BUFFER = 'invalidStockBufferValue';
  const LOG_KEY_SKIPPED_FULL = 'fullInventorySkipped';
  const LOG_KEY_LONG_RUN_FULL = `fullInventoryLongRunning`;
  const LOG_KEY_SKIPPED_PARTIAL = 'partialInventorySkipped';
  const LOG_KEY_LONG_RUN_PARTIAL = 'partialInventoryLongRunning';
  const LOG_KEY_NO_SYNCS = 'noInventorySyncs';

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

  /** @var InventoryStatusService */
  private $statusService;

  /** @var InventoryService */
  private $inventoryService;

  /** @var InventoryMapper */
  private $inventoryMapper;

  /** @var LoggerContract */
  private $logger;

  /** @var ExternalLogs */
  private $externalLogs;

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
    ExternalLogs $externalLogs,
    LogSenderService $logSenderService
  ) {
    $this->inventoryService = $inventoryService;
    $this->inventoryMapper = $inventoryMapper;
    $this->statusService = $statusService;
    $this->configHelper = $configHelper;
    $this->logger = $logger;
    $this->externalLogs = $externalLogs;
    $this->logSenderService = $logSenderService;
  }

  /**
   * Validate a request for inventory update
   * @param RequestDTO $inventoryRequestDTO
   * @param LoggerContract $this->logger
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
   * @param bool $manual flag for logs which indicates that the sync was manually induced
   *
   * @return array
   */
  public function sync(bool $fullInventory, bool $manual = false): array
  {
    $inventorySaveTotal = 0;
    $inventorySaveSuccess = 0;
    $inventorySaveFail = 0;
    $saveInventoryDuration = 0;
    $savedInventoryDuration = 0;

    try {
      $lastStartTime = $this->statusService->getLastAttemptTime($fullInventory);

      if (!$fullInventory && (!isset($lastStartTime) || $lastStartTime <= 0)) {
        $lastFull = $this->statusService->getLastAttemptTime(true);
        if (!isset($lastFull) || $lastFull <= 0) {
          $fullInventory = true;

          $this->logger->info(
            TranslationHelper::getLoggerKey(self::LOG_KEY_NO_SYNCS),
            [
              'additionalInfo' => [],
              'method' => __METHOD__
            ]
          );

          $this->externalLogs->addInfoLog("Forcing a full inventory sync as there are no syncs on record");
        }
      }

      // potential race conditions - change service management strategy in a future update
      // (but this is better than letting the old UpdateFullInventoryStatusCron randomly change service states)
      if ($this->statusService->isInventoryRunning($fullInventory) && !$this->serviceHasBeenRunningTooLong($fullInventory)) {

        $stateArray = $this->statusService->getServiceState($fullInventory);

        $logKey = self::LOG_KEY_SKIPPED_PARTIAL;
        if ($fullInventory) {
          $logKey = self::LOG_KEY_SKIPPED_FULL;
        }

        $this->logger->info(TranslationHelper::getLoggerKey($logKey), [
          'additionalInfo' => ['manual' => (string) $manual, 'startedAt' => $lastStartTime, 'state' => $stateArray],
          'method' => __METHOD__
        ]);
        $this->externalLogs->addErrorLog(($manual ? "Manual " : "Automatic") . "Inventory sync BLOCKED - already running");

        // early exit
        return $stateArray;
      }

      $fields = $this->getResultFields();
      /* Page size is tuned for a balance between memory usage (in plentymarkets) and number of transactions  */
      $fields['itemsPerPage'] = AbstractConfigHelper::INVENTORY_ITEMS_PER_PAGE;

      $filters = $this->getDefaultFilters();
      if (!$fullInventory) {
        self::applyTimeFilter($lastStartTime, $filters);
      }

      // look up item mapping method at this level to ensure consistency and improve efficiency
      $itemMappingMethod = $this->configHelper->getItemMappingMethod();

      // TODO: remove dependency on VariationSearchRepositoryContract by replacing with a Wayfair wrapper
      /** @var VariationSearchRepositoryContract */
      $variationSearchRepository = pluginApp(VariationSearchRepositoryContract::class);

      $variationSearchRepository->setFilters($filters);

      // stock buffer should be the same across all pages of inventory
      $stockBuffer = $this->getNormalizedStockBuffer();


      $this->logger->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_START),
        [
          'additionalInfo' => ['fullInventory' => (string) $fullInventory],
          'method' => __METHOD__
        ]
      );

      $this->externalLogs->addInfoLog("Starting " . ($manual ? "Manual " : "Automatic ") . ($fullInventory ? "Full " : "Partial") . "inventory sync.");

      $this->statusService->markInventoryStarted(true, $manual);

      $page = 0;
      $amtOfDtosForPage = 0;

      do {

        $msAtPageStart = TimeHelper::getMilliseconds();

        /** @var RequestDTO[] $validatedRequestDTOs collection of DTOs to include in a bulk update*/
        $validatedRequestDTOs = [];
        $fields['page'] = (string) $page;
        $variationSearchRepository->setSearchParams($fields);
        $response = $variationSearchRepository->search();

        /** @var array $variationWithStock information about a single Variation, including stock for each Warehouse */
        foreach ($response->getResult() as $variationWithStock) {
          /** @var RequestDTO[] $rawInventoryRequestDTOs non-normalized candidates for inclusion in bulk update */
          $rawInventoryRequestDTOs = $this->inventoryMapper->createInventoryDTOsFromVariation($variationWithStock, $itemMappingMethod, $stockBuffer);
          foreach ($rawInventoryRequestDTOs as $dto) {
            // validation method will output logs on failure
            if ($this->validateInventoryRequestData($dto, $this->logger)) {
              $validatedRequestDTOs[] = $dto;
            }
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

          $this->externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': No items to update');
        } else {
          $this->externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': ' . (string) $amtOfDtosForPage . ' updates to send');

          $this->logger->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
            [
              'additionalInfo' => ['info' => (string) $amtOfDtosForPage . ' updates to send'],
              'method' => __METHOD__
            ]
          );

          $saveInventoryDuration += TimeHelper::getMilliseconds() - $msAtPageStart;
          $msBeforeUpdate = TimeHelper::getMilliseconds();

          $inventorySaveTotal +=  $amtOfDtosForPage;

          $dto = $this->inventoryService->updateBulk($validatedRequestDTOs, $fullInventory);

          $savedInventoryDuration += TimeHelper::getMilliseconds() - $msBeforeUpdate;

          $inventorySaveSuccess += count($validatedRequestDTOs) - count($dto->getErrors());
          $inventorySaveFail += count($dto->getErrors());
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

      if ($inventorySaveFail > 0) {
        $this->statusService->markInventoryFailed($fullInventory, $manual);
      } else {
        $this->statusService->markInventoryComplete($fullInventory, $manual);
      }

      return $this->statusService->getServiceState($fullInventory);
    } catch (\Exception $e) {
      $this->externalLogs->addInventoryLog('Inventory: ' . $e->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false);

      $this->logger->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_ERROR),
        [
          'additionalInfo' => [
            'exception' => $e,
            'message' => $e->getMessage(),
            'stackTrace' => $e->getTrace(),
          ],
          'method' => __METHOD__
        ]
      );

      // bulk update failed, so everything we were going to save should be considered failing.
      // (we want the failure amount to be more than zero in order for client to know this failed.)
      $inventorySaveFail += $amtOfDtosForPage;

      $this->statusService->markInventoryFailed(true, $manual, $e);
    } finally {
      // FIXME: the 'inventorySave' and 'inventorySaved' log types are too similar
      // TODO: determine if changing the types will impact kibana / grafana / influxDB before changing
      $this->externalLogs->addInventoryLog('Inventory save', 'inventorySave' . ($fullInventory ? 'Full' : ''), $inventorySaveTotal, $saveInventoryDuration);
      $this->externalLogs->addInventoryLog('Inventory save', 'inventorySaved' . ($fullInventory ? 'Full' : ''), $inventorySaveSuccess, $saveInventoryDuration);
      $this->externalLogs->addInventoryLog('Inventory save failed', 'inventorySaveFailed' . ($fullInventory ? 'Full' : ''), $inventorySaveFail, $savedInventoryDuration);

      $this->externalLogs->addInfoLog("Finished " . ($manual ? "Manual " : "Automatic ") . ($fullInventory ? "Full " : "Partial") . "inventory sync.");

      $this->logger->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_END),
        [
          'additionalInfo' => ['fullInventory' => (string) $fullInventory],
          'method' => __METHOD__
        ]
      );

      $this->logSenderService->execute($this->externalLogs->getLogs());
    }
  }

  /**
   * @return array
   */
  public function getResultFields()
  {
    return [
      'with' => [
        'stock' => true,
        'variationSkus' => true,
        'variationBarcodes' => true,
        'variationMarkets' => true
      ]
    ];
  }

  /**
   * @param bool $fullInventory
   *
   * @return array
   */
  private function getDefaultFilters(): array
  {
    /**
     * @var AbstractConfigHelper $configHelper
     */
    $configHelper = pluginApp(AbstractConfigHelper::class);

    $filter = [
      'isActive' => true
    ];

    if (!$configHelper->isAllItemsActive()) {
      $filter['referrerId'] = [$configHelper->getOrderReferrerValue()];
    }

    return $filter;
  }

  private static function applyTimeFilter(int $startOfWindow, $filters)
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
   * @param AbstractConfigHelper $configHelper
   * @param LoggerContract $this->logger
   * @return int
   */
  private function getNormalizedStockBuffer()
  {
    $stockBuffer = null;
    if (isset($configHelper)) {
      $stockBuffer = $configHelper->getStockBufferValue();
    }

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
   *
   * @return boolean
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
