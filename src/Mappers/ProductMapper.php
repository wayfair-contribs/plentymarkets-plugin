<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\General\ProductDTO;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Wayfair\Helpers\TranslationHelper;

class ProductMapper
{

  const LOG_KEY_VARIATION_NOT_FOUND = 'variationNotFound';

  /**
   * @var VariationSearchRepositoryContract
   */
  private $variationSearchRepositoryContract;

  /** @var LoggerContract */
  private $loggerContract;

  /**
   * ProductMapper constructor.
   *
   * @param VariationSearchRepositoryContract $variationSearchRepositoryContract
   */
  public function __construct(
    VariationSearchRepositoryContract $variationSearchRepositoryContract,
    LoggerContract $loggerContract
  ) {
    $this->variationSearchRepositoryContract = $variationSearchRepositoryContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * @param ProductDTO $dto
   * @param int        $referrerId
   * @param string     $warehouseId
   * @param string     $poNumber
   *
   * @return array
   */
  public function map(ProductDTO $dto, int $referrerId, string $warehouseId, string $poNumber, string $itemMappingMethod): array
  {
    $partNumber = $dto->getPartNumber();


    $variationId = $this->getVariationId((string) $partNumber, $itemMappingMethod, $poNumber);

    // Init amounts
    $amounts = [
      [
        'isSystemCurrency' => true,
        'currency' => 'EUR',
        'exchangeRate' => 1,
        'purchasePrice' => $dto->getPrice(),
        'priceOriginalNet' => $dto->getPrice()
      ]
    ];
    // Init properties
    $properties = [
      [
        'typeId' => OrderPropertyType::WEIGHT,
        'value' => (string) $dto->getTotalWeight()
      ]
    ];
    if ($warehouseId) {
      $properties[] = [
        'typeId' => OrderPropertyType::WAREHOUSE,
        'value' => $warehouseId
      ];
    }
    $data = [
      'typeId' => $variationId ? OrderItemType::TYPE_VARIATION : OrderItemType::TYPE_UNASSIGEND_VARIATION,
      'referrerId' => $referrerId,
      'itemVariationId' => $variationId,
      'quantity' => floatval($dto->getQuantity()),
      'orderItemName' => $dto->getName(),
      'amounts' => $amounts,
      'properties' => $properties
    ];

    return $data;
  }

  /**
   * @param string $partNumber
   * @param string @itemMappingMethod
   * @param string $poNumber
   *
   * @return int
   */
  public function getVariationId(string $partNumber, string $itemMappingMethod, string $poNumber): int
  {
    $filters = [$itemMappingMethod => $partNumber];
    $this->variationSearchRepositoryContract->setFilters($filters);
    $result = $this->variationSearchRepositoryContract->search();
    foreach ($result->getResult() as $variation) {
      return $variation['id'];
    }

    $this->loggerContract
      ->warning(
        TranslationHelper::getLoggerKey(self::LOG_KEY_VARIATION_NOT_FOUND),
        [
          'additionalInfo' => [
            'itemMappingMethod' => $itemMappingMethod,
            'partNumber' => $partNumber,
            'poNumber' => $poNumber
          ],
          'referenceType' => $itemMappingMethod,
          'referenceValue' => $partNumber,
          'method' => __METHOD__
        ]
      );

    return 0;
  }
}
