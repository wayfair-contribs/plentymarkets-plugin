<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Test;

use Wayfair\Core\Contracts\LoggerContract;

class TestTimeLogger implements LoggerContract
{

    public function debug(string $code, $loggingInfo = null)
    {
        printf("\n[DEBUG]: %s\n", $code);
    }

    public function info(string $code, $loggingInfo = null)
    {
        printf("\n[INFO]: %s\n", $code);
    }

    public function error(string $code, $loggingInfo = null)
    {
        printf("\n[ERROR]: %s\n", $code);
    }

    public function warning(string $code, $loggingInfo = null)
    {
        printf("\n[WARNING]: %s\n", $code);
    }
}
