<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

class VariationSkuDataFactory
{
    const COL_MARKET_ID = 'marketId';
    const COL_SKU = 'sku';

    public function create(string $marketId, string $sku): array
    {
        return [
            self::COL_MARKET_ID => $marketId,
            self::COL_SKU => $sku
        ];
    }
}
