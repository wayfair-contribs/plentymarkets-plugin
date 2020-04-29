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
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Core\Helpers\TimeHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Mappers\InventoryMapper;
use Wayfair\Models\ExternalLogs;

class InventoryUpdateService
{
  const LOG_KEY_DEBUG = 'debugInventoryUpdate';
  const LOG_KEY_INVALID_INVENTORY_UPDATE = 'invalidInventoryUpdate';
  const LOG_KEY_INVENTORY_UPDATE_END = 'inventoryUpdateEnd';
  const LOG_KEY_INVENTORY_UPDATE_ERROR = 'inventoryUpdateError';
  const LOG_KEY_INVENTORY_UPDATE_START = 'inventoryUpdateStart';
  const LOG_KEY_NEGATIVE_INVENTORY = 'negativeInventory';

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

    if ($inventoryRequestDTO->getQuantityOnHand() < 0) {
      $loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_NEGATIVE_INVENTORY), [
          'additionalInfo' => ['data' => $inventoryRequestDTO->toArray()],
          'method' => __METHOD__
        ]
      );

      $inventoryRequestDTO->setQuantityOnHand(0);
    }

    $supplierId = $inventoryRequestDTO->getSupplierId();
    if (isset($supplierId) && !empty($inventoryRequestDTO->getSupplierPartNumber())) {
      return true;
    }

    $loggerContract
      ->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVALID_INVENTORY_UPDATE), [
          'additionalInfo' => [
            'message' => 'inventory request data is invalid',
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
    /** @var array $syncResultObjects collection of the individual results of bulk update actions against the Wayfair API */
    $syncResultObjects = [];

    $loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_START), [
        'additionalInfo' => ['fullInventory' => (string)$fullInventory],
        'method' => __METHOD__
      ]
    );


    $page = 0;
    $inventorySaveTotal = 0;
    $inventorySaveSuccess = 0;
    $inventorySaveFail = 0;
    $saveInventoryDuration = 0;
    $savedInventoryDuration = 0;

    try {
      $fields = $this->getResultFields();
      /* Page size is tuned for a balance between memory usage (in plentymarkets) and number of transactions  */
      $fields['itemsPerPage'] = ConfigHelperContract::INVENTORY_ITEMS_PER_PAGE;
      $variationSearchRepository->setFilters($this->getFilters($fullInventory));

      do {

        $msAtPageStart = TimeHelper::getMilliseconds();

        $listOfItemsToBeUpdated = [];
        $fields['page'] = (string)$page;
        $variationSearchRepository->setSearchParams($fields);
        $response = $variationSearchRepository->search();

        foreach ($response->getResult() as $variationsWithStock) {
          /**
           * @var RequestDTO $inventoryRequestDTO
           */
          $inventoryRequestDTO = $inventoryMapper->map($variationsWithStock);

          if ($this->validateInventoryRequestData($inventoryRequestDTO, $loggerContract)) {
            array_push($listOfItemsToBeUpdated, $inventoryRequestDTO);
          }
        }

        if (count($listOfItemsToBeUpdated) == 0) {
          $loggerContract
            ->debug(
              TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG), [
                'additionalInfo' => ['info' => 'No items to update'],
                'method' => __METHOD__
              ]
            );

          $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': No items to update');
        } else {
          $amt_to_update = count($listOfItemsToBeUpdated);

          $externalLogs->addInfoLog('Inventory ' . ($fullInventory ? 'Full' : '') . ': ' . (string)$amt_to_update . ' items to update');

          $loggerContract->debug(
            TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG), [
              'additionalInfo' => ['info' => (string)$amt_to_update . ' items to update'],
              'method' => __METHOD__
            ]
          );

          $saveInventoryDuration += TimeHelper::getMilliseconds() - $msAtPageStart;
          $msBeforeUpdate = TimeHelper::getMilliseconds();

          $dto = $inventoryService->updateBulk($listOfItemsToBeUpdated, $fullInventory);

          $savedInventoryDuration += TimeHelper::getMilliseconds() - $msBeforeUpdate;
          $inventorySaveTotal += count($listOfItemsToBeUpdated);
          $inventorySaveSuccess += count($listOfItemsToBeUpdated) - count($dto->getErrors());
          $inventorySaveFail += count($dto->getErrors());

          $syncResultObjects[] = $dto->toArray();
        }

        $loggerContract->debug(
          TranslationHelper::getLoggerKey(self::LOG_KEY_DEBUG), [
            'additionalInfo' => ['fullInventory' => (string)$fullInventory, 'page_num' => (string)$page, 'info' => 'page done'],
            'method' => __METHOD__
          ]
        );

        $page++;
      } while (!$response->isLastPage());

      $loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_END), [
          'additionalInfo' => ['fullInventory' => (string)$fullInventory],
          'method' => __METHOD__
        ]
      );

    } catch (\Exception $e) {
      // TODO: consider failing out of one item / one page instead of failing the whole sync
      $externalLogs->addInventoryLog('Inventory: ' . $e->getMessage(), 'inventoryFailed' . ($fullInventory ? 'Full' : ''), 1, 0, false);

      $exceptionType = get_class($e);
      $msg = $e->getMessage();
      $stack = $e->getTrace();
      $lenStack = count($stack);
      $lenMsg = strlen($msg);

      if ($lenStack > 2)
      {
        // truncate the stack to avoid PM saying the log message is too large
        $stack = array_slice($stack, 0, 2);
        $stack[] = '...';
      }

      if ($lenMsg > 64)
      {
        // message is over 300k here!
        $msg = substr($msg, 0, 64);
      }

      $loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_UPDATE_ERROR),
        [
          'additionalInfo' => [
            'exceptionType' => $exceptionType,
            'message' => $msg,
            'stackTrace' => $stack,
            'lenStack' => $lenStack,
            'lenMsg' => $lenMsg
          ],
          'method' => __METHOD__
        ]
      );

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

    return $syncResultObjects;
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
     * @var ConfigHelperContract $configHelper
     */
    $configHelper = pluginApp(ConfigHelperContract::class);

    $filter = [
      'isActive' => true
    ];

    if (!$configHelper->isAllItemsActive()) {
      $filter['referrerId'] = [$configHelper->getOrderReferrerValue()];
    }

    if (!$fullInventory) {
      $filter['updatedBetween'] = [
        'timestampFrom' => time() - ConfigHelperContract::SECONDS_INTERVAL_FOR_INVENTORY,
        'timestampTo' => time(),
      ];
    }

    return $filter;
  }
}
