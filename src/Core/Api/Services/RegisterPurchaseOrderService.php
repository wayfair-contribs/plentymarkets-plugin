<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Contracts\RegisterPurchaseOrderContract;
use Wayfair\Core\Dto\RegisterPurchaseOrder\RequestDTO;
use Wayfair\Core\Dto\RegisterPurchaseOrder\ResponseDTO;
use Wayfair\Core\Exceptions\DryRunNotSupportedException;
use Wayfair\Core\Exceptions\GraphQLQueryException;
use Wayfair\Helpers\TranslationHelper;

/**
 * Class RegisterPurchaseOrderService
 *
 * @package Wayfair\Core\Api\Services
 */
class RegisterPurchaseOrderService extends APIService implements RegisterPurchaseOrderContract
{
  const LOG_KEY_UNABLE_TO_REGISTER_ORDER = 'unableToRegisterOrder';

  /**
   * @param RequestDTO $requestDTO
   *
   * @return ResponseDTO
   * @throws \Exception
   */
  public function register(RequestDTO $requestDTO): ResponseDTO
  {

    $dryRunVal = $this->configHelper->getDryRun();
    if(filter_var($dryRunVal, FILTER_VALIDATE_BOOLEAN))
    {
      // dry run is NOT supported in the register mutation.
      // TODO: if/when 'dryRun' mode uses the Wayfair API Sandbox, stop throwing the Exception
      throw new DryRunNotSupportedException("Purchase Order Registration not allowed when in dryRun mode");
    }

    $query = 'mutation purchaseOrders { '
      . 'purchaseOrders { '
      . 'register( '
      . 'registrationInput: { '
      . 'poNumber: "' . $requestDTO->getPoNumber() . '" '
      . (empty($requestDTO->getWarehouseId()) ? ' ' : 'warehouseId: "' . $requestDTO->getWarehouseId() . '" ')
      . '}) '
      . '{ id, eventDate ,pickupDate, poNumber, purchaseOrder { poNumber, storePrefix }, billOfLading { url }, consolidatedShippingLabel { url }, customsDocument { required, url } } '
      . '} '
      . '}';
    $this->loggerContract
      ->info(TranslationHelper::getLoggerKey('attemptingRegisterMutationQuery'), ['additionalInfo' => ['query' => $query], 'method' => __METHOD__]);
    try {
      $response = $this->query($query);

      if (!isset($response) or empty($response)) {
        throw new GraphQLQueryException("Did not get query response");
      }

      $responseBody = $response->getBodyAsArray();
      $this->loggerContract
        ->debug(TranslationHelper::getLoggerKey('registerMutationQueryResponse'), ['additionalInfo' => ['responseBody' => $responseBody], 'method' => __METHOD__]);

      $errors = $response->getError();
      if ((isset($errors) && !empty($errors)) || empty($responseBody['data']['purchaseOrders']['register'])) {
        $this->loggerContract
          ->error(
            TranslationHelper::getLoggerKey(self::LOG_KEY_UNABLE_TO_REGISTER_ORDER),
            [
              'additionalInfo' => ['error' => $errors],
              'method' => __METHOD__,
              'referenceType' => 'poNumber',
              'referenceValue' => $requestDTO->getPoNumber()
            ]
          );
        throw new \Exception(TranslationHelper::getLoggerMessage(self::LOG_KEY_UNABLE_TO_REGISTER_ORDER));
      }
      $register = $responseBody['data']['purchaseOrders']['register'];

      return ResponseDTO::createFromArray($register);
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_UNABLE_TO_REGISTER_ORDER),
          [
            'additionalInfo' => ['message' => $e->getMessage()],
            'method' => __METHOD__,
            'referenceType' => 'poNumber',
            'referenceValue' => $requestDTO->getPoNumber()
          ]
        );
      throw $e;
    }
  }
}
