<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Mappers;

use Plenty\Modules\Item\Variation\Contracts\VariationSearchRepositoryContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\General\ProductDTO;
use Plenty\Modules\Order\Models\OrderItemType;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Repositories\KeyValueRepository;

class ProductMapper
{
  /**
   * @var VariationSearchRepositoryContract
   */
  public $variationSearchRepositoryContract;

  /**
   * @var KeyValueRepository
   */
  public $keyValueRepository;

  /**
   * ProductMapper constructor.
   *
   * @param VariationSearchRepositoryContract $variationSearchRepositoryContract
   * @param KeyValueRepository                $keyValueRepository
   */
  public function __construct(
    VariationSearchRepositoryContract $variationSearchRepositoryContract,
    KeyValueRepository $keyValueRepository
  ) {
    $this->variationSearchRepositoryContract = $variationSearchRepositoryContract;
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * @param ProductDTO $dto
   * @param int        $referrerId
   * @param string     $warehouseId
   * @param string     $poNumber
   *
   * @return array
   */
  public function map(ProductDTO $dto, int $referrerId, string $warehouseId, string $poNumber): array
  {

    $itemMappingMethod = $this->keyValueRepository->get(AbstractConfigHelper::SETTINGS_DEFAULT_ITEM_MAPPING_METHOD);
    $filterMapping = [
      AbstractConfigHelper::ITEM_MAPPING_SKU,
      AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER,
      AbstractConfigHelper::ITEM_MAPPING_EAN
    ];

    $partNumber = $dto->getPartNumber();
    if (empty($itemMappingMethod) || !in_array($itemMappingMethod, $filterMapping)) {
      $itemMappingMethod = AbstractConfigHelper::ITEM_MAPPING_VARIATION_NUMBER;
    }

    $filters = [$itemMappingMethod => $partNumber];
    $variationId = $this->getVariationId((string)$partNumber, $filters, $poNumber);

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
        'value' => (string)$dto->getTotalWeight()
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
   * @param string $poNumber
   *
   * @return int
   */
  public function getVariationId(string $partNumber, array $filters, string $poNumber): int
  {
    $this->variationSearchRepositoryContract->setFilters($filters);
    $result = $this->variationSearchRepositoryContract->search();
    foreach ($result->getResult() as $variation) {
      return $variation['id'];
    }
    /**
     * @var LoggerContract $loggerContract
     */
    $loggerContract = pluginApp(LoggerContract::class);
    $loggerContract
        ->warning(
          TranslationHelper::getLoggerKey('variationNotFound'),
          [
            'additionalInfo' => ['partNumber' => $partNumber],
            'referenceType' => 'poNumber',
            'referenceValue' => $poNumber,
            'method' => __METHOD__
          ]
        );

    return 0;
  }
}
