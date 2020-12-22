<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'VariationBarcodeDataFactory.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'VariationSkuDataFactory.php');

use Wayfair\PlentyMockets\Factories\VariationBarcodeDataFactory;
use Wayfair\PlentyMockets\Factories\VariationSkuDataFactory;

class VariationDataFactory
{
    const COL_ID = 'id';
    const COL_NUMBER = 'number';
    const COL_BARCODES = 'variationBarcodes';
    const COL_SKUS = 'variationSkus';

    private static $nextId = 1;

    public function __construct()
    {
        $this->barcodeDataFactory = new VariationBarcodeDataFactory();
        $this->skuDataFactory = new VariationSkuDataFactory();
    }

    public function create(int $amtBarcodes = 0, $skuMarkets = []): array
    {
        $id = self::$nextId++;
        $barcodes = [];
        $skus = [];

        if ($amtBarcodes) {
            $barcodeDataFactory = new VariationBarcodeDataFactory();
            for ($i=1; $i <= $amtBarcodes; $i++) {
                $barcodes[] = $barcodeDataFactory->create('barcode_' . $i);
            }
        }

        if (isset($skuMarkets) && count($skuMarkets) > 0) {
            $skuDataFactory = new VariationSkuDataFactory();
            foreach ($skuMarkets as $marketId => $value) {
                $skus[] = $skuDataFactory->create($marketId, 'sku_' . $marketId);
            }
        }

        return [
            self::COL_ID => $id,
            self::COL_NUMBER => 'variation_' . $id,
            self::COL_BARCODES => $barcodes,
            self::COL_SKUS => $skus
        ];
    }
}
