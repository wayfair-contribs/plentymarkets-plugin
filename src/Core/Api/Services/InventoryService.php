<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Contracts\AuthContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\Inventory\ResponseDTO;
use Wayfair\Core\Exceptions\GraphQLQueryException;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Factories\ExternalLogsFactory;
use Wayfair\Factories\InventoryResponseDtoFactory;

/**
 * Class InventoryService
 *
 * @package Wayfair\Core\Api\Services
 */
class InventoryService extends APIService
{
  const LOG_KEY_INVENTORY_QUERY_ERROR = 'inventoryQueryError';
  const LOG_KEY_INVENTORY_QUERY_DEBUG = 'debugInventoryQuery';

  /** @var LogSenderService */
  private $logSenderService;

  /** @var ExternalLogsFactory */
  private $externalLogsFactory;

  private $inventoryResponseDtoFactory;

  /**
   * @param ClientInterfaceContract $clientInterfaceContract
   * @param AuthContract            $authContract
   * @param AbstractConfigHelper    $configHelper
   * @param LoggerContract          $loggerContract
   */
  public function __construct(
    ClientInterfaceContract $clientInterfaceContract,
    AuthContract $authContract,
    AbstractConfigHelper $configHelper,
    LoggerContract $loggerContract,
    LogSenderService $logSenderService,
    ExternalLogsFactory $externalLogsFactory,
    InventoryResponseDtoFactory $inventoryResponseDtoFactory

  ) {
    parent::__construct(
      $clientInterfaceContract,
      $authContract,
      $configHelper,
      $loggerContract,
      $logSenderService,
      $externalLogsFactory
    );
    $this->inventoryResponseDtoFactory = $inventoryResponseDtoFactory;
  }

  /**
   * @param array $listOfRequestDTOs
   *
   * @return array
   */
  private function buildQuery(array $listOfRequestDTOs)
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
      . 'feedKind: DIFFERENTIAL,'
      . 'dryRun: ' . $this->configHelper->getDryRun()
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
   *
   * @return ResponseDTO
   * @throws \Exception
   */
  public function updateBulk(array $listOfRequestDto)
  {
    $externalLogs = $this->externalLogsFactory->create();

    try {

      $queryData = $this->buildQuery($listOfRequestDto);

      $response = $this->query($queryData['query'], 'post', $queryData['variables']);

      if (!isset($response) or empty($response)) {
        throw new GraphQLQueryException("Did not get query response");
      }

      $responseBody = $response->getBodyAsArray();
      $errors = $response->getError();

      if (isset($errors) && !empty($errors)) {
        throw new \Exception("Unable to update inventory due to errors: " . json_encode($errors));
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

      return $this->inventoryResponseDtoFactory->create($inventory['save']);
    } finally {
      if (count($externalLogs->getLogs())) {
        $this->logSenderService->execute($externalLogs->getLogs());
      }
    }
  }
}
