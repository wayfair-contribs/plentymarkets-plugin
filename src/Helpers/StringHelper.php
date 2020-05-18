<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Helpers;

/**
 * Utilities for working with Strings
 */
abstract class StringHelper {

    const MASK_TEMPLATE = '********';

    /**
     * A quick string mask with a static output length.
     * Reveals at most 2 characters of the input string.
     *
     * @param string $input
     * @return string
     */
    public static function mask(string $input): string
    {
        if (! $input)
        {
            return '';
        }

        $masked = self::MASK_TEMPLATE;
        $lenMasked = strlen($masked);

        $lenInput = strlen($input);
        if ($lenInput > 1)
        {
           $masked[0] = $input[0];
        }

        if ($lenInput > 2)
        {
            $masked[$lenMasked -1] = $input[$lenInput -1];
        }

        return $masked;
    }

}
