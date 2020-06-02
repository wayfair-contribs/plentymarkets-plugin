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

    /**
     * A function that will safely truncate a string (meaning truncation will not happen within a specific string).
     *
     * @param string $text
     * @param int $maxchar
     * @param string $end
     * @return string
     */
    function truncateString($text, $maxchar, $end = '...'): string
    {
        if (strlen($text) > $maxchar || $text == '') {
            $words = preg_split('/\s/', $text);
            $output = '';
            $i      = 0;
            while (1) {
                $length = strlen($output) + strlen($words[$i]);
                if ($length > $maxchar) {
                    break;
                } else {
                    $output .= " " . $words[$i];
                    ++$i;
                }
            }
            $output .= $end;
        } else {
            $output = $text;
        }
        return $output;
    }

}
