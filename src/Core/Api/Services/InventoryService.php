<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Dto\Inventory\ResponseDTO;
use Wayfair\Core\Exceptions\GraphQLQueryException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;
use Wayfair\Models\ExternalLogs;

/**
 * Class InventoryService
 *
 * @package Wayfair\Core\Api\Services
 */
class InventoryService extends APIService
{
  const LOG_KEY_INVENTORY_QUERY_ERROR = 'inventoryQueryError';
  const LOG_KEY_INVENTORY_QUERY_DEBUG = 'debugInventoryQuery';

  /**
   *
   * @return WayfairResponse
   */
  public function fetch()
  {
    $query = 'query inventory {'
      . 'inventory(limit: 2)'
      . '{'
      . 'supplierPartNumber,  quantityOnHand,  quantityBackordered,  quantityOnOrder,  discontinued,  itemNextAvailabilityDate'
      . '}'
      . '}';
    try {

      $response = $this->query($query);

      if (!isset($response) or empty($response)) {
        throw new GraphQLQueryException("Did not get query response");
      }

      $this->loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_QUERY_DEBUG),
        [
          'additionalInfo' => [
            'query' => $query,
            'response' => $response
          ],
          'method' => __METHOD__
        ]
      );

      if ($response->hasErrors()) {
        $error = $response->getError();
        throw new \Exception("Response from inventory query has errors:" . \json_encode($error));
      }

      return $response;

    } catch (\Exception $e) {

      $this->loggerContract->error(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_QUERY_ERROR),
        [
          'additionalInfo' => [
            'exception' => $e,
            'message' => $e->getMessage(),
            'stackTrace' => $e->getTrace(),
          ],
          'method' => __METHOD__
        ]
      );

      return null;
    }
  }

  /**
   * @param array $listOfRequestDTOs
   * @param bool $fullInventory
   *
   * @return array
   */
  public function buildQuery(array $listOfRequestDTOs, bool $fullInventory = false)
  {
    /**
     * @var AbstractConfigHelper $configHelper
     */
    $configHelper = pluginApp(AbstractConfigHelper::class);
    $fullData = [];
    foreach ($listOfRequestDTOs as $requestDTO) {
      $fullData[] = [
        'supplier_id' => $requestDTO->getSupplierId(),
        'supplier_part_number' => $requestDTO->getSupplierPartNumber(),
        'quantity_on_hand' => $requestDTO->getQuantityOnHand(),
        'quantity_backordered' => $requestDTO->getQuantityBackorder(),
        'quantity_on_order' => $requestDTO->getQuantityOnOrder(),
        'item_next_availability_date' => $requestDTO->getItemNextAvailabilityDate(),
        'discontinued' => $requestDTO->isDiscontinued(),
        'product_name_and_options' => $requestDTO->getProductNameAndOptions()
      ];
    }

    $query = 'mutation save($inventory: [inventoryInput]!) {'
      . 'inventory {'
      . 'save('
      . 'inventory: $inventory,'
      . 'feedKind: DIFFERENTIAL,'
      . 'dryRun: ' . $configHelper->getDryRun()
      . ') {'
      . 'id,'
      . 'handle,'
      . 'status,'
      . 'submittedAt,'
      . 'completedAt,'
      . 'errors {'
      . 'key'
      . '}'
      . '}'
      . '}'
      . '}';
    return ['query' => $query, 'variables' => ['inventory' => $fullData]];
  }

  /**
   * @param array $listOfRequestDto
   * @param bool $fullInventory
   *
   * @return ResponseDTO
   * @throws \Exception
   */
  public function updateBulk(array $listOfRequestDto, bool $fullInventory = false)
  {
    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    try {

      $queryData = $this->buildQuery($listOfRequestDto, $fullInventory);

      $response = $this->query($queryData['query'], 'post', $queryData['variables']);

      if (!isset($response) or empty($response)) {
        throw new GraphQLQueryException("Did not get query response");
      }

      $responseBody = $response->getBodyAsArray();

      // FIXME: use $response->getError()
      if (isset($responseBody['errors'])) {
        throw new \Exception("Unable to update inventory due to errors." .
          " Response from  Wayfair: " . \json_encode($responseBody));
      }

      if (!isset($responseBody['data'])) {
        throw new \Exception("Unable to update inventory - no data in response. " .
          " Response from  Wayfair: " . \json_encode($responseBody));
      }

      $response_data_array = $responseBody['data'];

      if (!isset($response_data_array['inventory'])) {
        throw new \Exception("Unable to update inventory - no inventory data in response. " .
          " Response from  Wayfair: " . \json_encode($responseBody));
      }

      $inventory = $response_data_array['inventory'];

      if (!isset($inventory['save'])) {
        throw new \Exception("Unable to update inventory - no save data in inventory area of response. " .
          " Response from  Wayfair: " . \json_encode($responseBody));
      }


      return ResponseDTO::createFromArray($inventory['save']);
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }
}
