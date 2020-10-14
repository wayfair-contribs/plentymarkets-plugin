<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Factories;

class VariationBarcodeDataFactory
{
    const COL_CODE = 'code';

    public function create(string $code): array
    {
        return [self::COL_CODE => $code];
    }
}
