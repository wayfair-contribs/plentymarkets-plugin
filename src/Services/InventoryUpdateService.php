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
  const LOG_KEY_NORMALIZING_INVENTORY = 'normalizingInventoryAmount';


  const INVENTORY_SAVE_TOTAL = 'inventorySaveTotal';
  const INVENTORY_SAVE_SUCCESS = 'inventorySaveSuccess';
  const INVENTORY_SAVE_FAIL = 'inventorySaveFail';
  const SAVE_INVENTORY_DURATION = 'saveInventoryDuration';
  const SAVED_INVENTORY_DURATION = 'savedInventoryDuration';
  const PAGES = 'pages';
  const ERROR_MESSAGE = 'errorMessage';

  /**
   * Validate a request for inventory update
   * @param RequestDTO $inventoryRequestDTO
   * @param LoggerContract $loggerContract
   * @return bool
   */
  private function validateInventoryRequestData($inventoryRequestDTO, $loggerContract): bool
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
        $newQuantity = -1;

        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_NORMALIZING_INVENTORY),
          [
            'additionalInfo' => [
              'data' => $inventoryRequestDTO->toArray(),
              'newQuantity' => $newQuantity
            ],
            'method' => __METHOD__
          ]
        );

        // the Wayfair Inventory system allows for a 'quantity on hand' value of -1,
        // which may indicate a discontinued product or an unknown quantity.
        // Any values lower than -1 are considered invalid and are being normalized to -1 here.
        $inventoryRequestDTO->setQuantityOnHand($newQuantity);
      }
    }
    else
    {
      $issues[] = "Quantity On Hand is missing";
    }

    if (!isset($issues) || empty($issues)) {
      return true;
    }

    // TODO: replace issues with translated messsages?
    $loggerContract
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
   * @param bool $fullInventory
   *
   * @return array
   */
  public function sync(bool $fullInventory = false): array
  {
    /** @var InventoryService $inventoryService */
    $inventoryService = pluginApp(InventoryService::class);
    /** @var InventoryMapper $inventoryMapper */
    $inventoryMapper = pluginApp(InventoryMapper::class);
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    /** @var VariationSearchRepositoryContract $variationSearchRepository */
    $variationSearchRepository = pluginApp(VariationSearchRepositoryContract::class);
    /** @var AbstractConfigHelper $configHelper */
    $configHelper = pluginApp(AbstractConfigHelper::class);

    // look up item mapping method at this level to ensure consistency and improve efficiency
    $itemMappingMethod = $configHelper->getItemMappingMethod();

    $loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_START),
      [
        'additionalInfo' => ['fullInventory' => (string) $fullInventory],
        'method' => __METHOD__
      ]
    );

    $page = 0;
    $inventorySaveTotal = 0;
    $inventorySaveSuccess = 0;
    $inventorySaveFail = 0;
    $saveInventoryDuration = 0;
    $savedInventoryDuration = 0;

    $errorMessage = null;
    $amtOfDtosForPage = 0;

    try {
      $fields = $this->getResultFields();
      /* Page size is tuned for a balance between memory usage (in plentymarkets) and number of transactions  */
      $fields['itemsPerPage'] = AbstractConfigHelper::INVENTORY_ITEMS_PER_PAGE;
      $variationSearchRepository->setFilters($this->getFilters($fullInventory));

      // stock buffer should be the same across all pages of inventory
      $stockBuffer = self::getNormalizedStockBuffer($configHelper, $loggerContract);

      do {

        $msAtPageStart = TimeHelper::getMilliseconds();

        /** @var RequestDTO[] $normalizedInventoryRequestDTOs collection of DTOs to include in a bulk update*/
        $normalizedInventoryRequestDTOs = [];
        $fields['page'] = (string) $page;
        $variationSearchRepository->setSearchParams($fields);
        $response = $variationSearchRepository->search();

        /** @var array $variationWithStock information about a single Variation, including stock for each Warehouse */
        foreach ($response->getResult() as $variationWithStock) {
          /** @var RequestDTO[] $rawInventoryRequestDTOs non-normalized candidates for inclusion in bulk update */
          $rawInventoryRequestDTOs = $inventoryMapper->createInventoryDTOsFromVariation($variationWithStock, $itemMappingMethod, $stockBuffer);
          foreach ($rawInventoryRequestDTOs as $dto) {
            // validation method will output logs on failure
            if ($this->validateInventoryRequestData($dto, $loggerContract)) {
              $normalizedInventoryRequestDTOs[] = $dto;
            }
          }
        }

        $amtOfDtosForPage = count($normalizedInventoryRequestDTOs);

        if ($amtOfDtosForPage <= 0) {
          $loggerContract
            ->debug(
              TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
              [
                'additionalInfo' => ['info' => 'No items to update'],
                'method' => __METHOD__
              ]
            );

          $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': No items to update');
        } else {
          $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': ' . (string) $amtOfDtosForPage . ' updates to send');

          $loggerContract->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG),
            [
              'additionalInfo' => ['info' => (string) $amtOfDtosForPage . ' updates to send'],
              'method' => __METHOD__
            ]
          );

          $saveInventoryDuration += TimeHelper::getMilliseconds() - $msAtPageStart;
          $msBeforeUpdate = TimeHelper::getMilliseconds();

          $inventorySaveTotal +=  $amtOfDtosForPage;

          $dto = $inventoryService->updateBulk($normalizedInventoryRequestDTOs, $fullInventory);

          $savedInventoryDuration += TimeHelper::getMilliseconds() - $msBeforeUpdate;

          $inventorySaveSuccess += count($normalizedInventoryRequestDTOs) - count($dto->getErrors());
          $inventorySaveFail += count($dto->getErrors());
        }

        $loggerContract->debug(
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

      $loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_END),
        [
          'additionalInfo' => ['fullInventory' => (string) $fullInventory],
          'method' => __METHOD__
        ]
      );
    } catch (\Exception $e) {
      // TODO: consider failing out of one item / one page instead of failing the whole sync
      $externalLogs->addInventoryLog('Inventory: ' . $e->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false);

      $loggerContract->error(
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

      $errorMessage = $e->getMessage();
      if (!isset($errorMessage))
      {
        $errorMessage = (string) $e;
      }

    } finally {
      // FIXME: the 'inventorySave' and 'inventorySaved' log types are too similar
      // TODO: determine if changing the types will impact kibana / graphana / influxDB before changing
      $externalLogs->addInventoryLog('Inventory save', 'inventorySave' . ($fullInventory ? 'Full' : ''), $inventorySaveTotal, $saveInventoryDuration);
      $externalLogs->addInventoryLog('Inventory save', 'inventorySaved' . ($fullInventory ? 'Full' : ''), $inventorySaveSuccess, $saveInventoryDuration);
      $externalLogs->addInventoryLog('Inventory save failed', 'inventorySaveFailed' . ($fullInventory ? 'Full' : ''), $inventorySaveFail, $savedInventoryDuration);

      /** @var LogSenderService $logSenderService */
      $logSenderService = pluginApp(LogSenderService::class);

      $logSenderService->execute($externalLogs->getLogs());
    }

    return [
      self::INVENTORY_SAVE_TOTAL => $inventorySaveTotal,
      self::INVENTORY_SAVE_SUCCESS => $inventorySaveSuccess,
      self::INVENTORY_SAVE_FAIL => $inventorySaveFail,
      self::SAVE_INVENTORY_DURATION => $saveInventoryDuration,
      self::SAVED_INVENTORY_DURATION => $savedInventoryDuration,
      self::PAGES => $page,
      self::ERROR_MESSAGE => $errorMessage
    ];
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
  public function getFilters(bool $fullInventory): array
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

    if (!$fullInventory) {
      $filter['updatedBetween'] = [
        'timestampFrom' => time() - AbstractConfigHelper::SECONDS_INTERVAL_FOR_INVENTORY,
        'timestampTo' => time(),
      ];
    }

    return $filter;
  }

  /**
   * Get the stock buffer value, normalized to 0
   *
   * @param AbstractConfigHelper $configHelper
   * @param LoggerContract $loggerContract
   * @return int
   */
  private static function getNormalizedStockBuffer($configHelper, $loggerContract)
  {
    $stockBuffer = null;
    if (isset($configHelper)) {
      $stockBuffer = $configHelper->getStockBufferValue();
    }

    if (isset($stockBuffer))
    {
      if ($stockBuffer >= 0)
      {
        return $stockBuffer;
      }

      // invalid value for buffer
      if (isset($loggerContract)) {
        $loggerContract->warning(
          TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_STOCK_BUFFER),
          [
            'additionalInfo' => ['stockBuffer' => $stockBuffer],
            'method' => __METHOD__
          ]
        );
      }
    }

    return 0;
  }
}
