<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Dto\Inventory\ResponseDTO;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Http\WayfairResponse;

/**
 * Class InventoryService
 *
 * @package Wayfair\Core\Api\Services
 */
class InventoryService extends APIService
{
  const LOG_KEY_INVENTORY_QUERY_ERROR = 'inventoryQueryError';
  const LOG_KEY_INVENTORY_QUERY_DEBUG = 'debugInventoryQuery';
  const LOG_KEY_INVENTORY_QUERY_BULK_ERROR = 'inventoryQueryBulkError';

  const RESPONSE_KEY_ERRORS = 'errors';
  const RESPONSE_KEY_DATA = 'data';
  const RESPONSE_DATA_KEY_INVENTORY = 'inventory';
  const RESPONSE_INVENTORY_KEY_SAVE = 'save';

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
        throw new \Exception("Response object from inventory query is null or empty");
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
      . 'feedKind: ' . ($fullInventory ? 'TRUE_UP' : 'DIFFERENTIAL') . ','
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
    try {
      $queryData = $this->buildQuery($listOfRequestDto, $fullInventory);

      $response = $this->query($queryData['query'], 'post', $queryData['variables']);

      if (!isset($response)) {
        throw new \Exception("Unable to update inventory - query action did not complete");
      }

      $responseBody = $response->getBodyAsArray();
      $reponseErrors = $responseBody[self::RESPONSE_KEY_ERRORS];

      if (isset($reponseErrors)) {
        throw new \Exception("Unable to update inventory due to errors." .
          " Errors from  Wayfair: " . \json_encode($reponseErrors));
      }

      $response_data_array = $responseBody[self::RESPONSE_KEY_DATA];

      if (!isset($response_data_array)) {
        throw new \Exception("Unable to update inventory - no data element in response.");
      }

      $inventory = $response_data_array[self::RESPONSE_DATA_KEY_INVENTORY];

      if (!isset($inventory)) {
        throw new \Exception("Unable to update inventory - no inventory data in response.");
      }

      $inventorySave = $inventory[self::RESPONSE_INVENTORY_KEY_SAVE];

      if (!isset($inventorySave)) {
        throw new \Exception("Unable to update inventory - no save data in inventory area of response.");
      }

      return ResponseDTO::createFromArray($inventorySave);
    } catch (\Exception $e)
    {
      // put the Wayfair response into the debug log as a SEPARATE message when there was an issue
      // this log may be too large. See ticket 'EM-100' about resolving that.
      $this->loggerContract->debug(
        TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_QUERY_BULK_ERROR),
        [
          'additionalInfo' => [
            'bulkInventoryResponse' => json_encode($response)
          ],
          'method' => __METHOD__
        ]
      );

      throw $e;
    }
  }
}
