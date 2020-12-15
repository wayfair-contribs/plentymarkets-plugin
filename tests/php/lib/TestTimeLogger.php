<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Test;

use Wayfair\Core\Contracts\LoggerContract;

class TestTimeLogger implements LoggerContract
{
    private $enableDebug;
    private $enableInfo;
    private $enableWarning;
    private $enableError;

    public function __construct($enableError = true, $enableWarning = true, $enableInfo = false, $enableDebug = false)
    {
        $this->enableError = $enableError;
        $this->enableWarning = $enableWarning;
        $this->enableInfo = $enableInfo;
        $this->enableDebug = $enableDebug;
    }

    public function debug(string $code, $loggingInfo = null)
    {
        if ($this->enableDebug) {
            printf("\n[DEBUG]: %s\n", $code);
        }
    }

    public function info(string $code, $loggingInfo = null)
    {
        if ($this->enableInfo) {
            printf("\n[INFO]: %s\n", $code);
        }
    }

    public function error(string $code, $loggingInfo = null)
    {
        if ($this->enableError) {
            printf("\n[ERROR]: %s\n", $code);
        }
    }

    public function warning(string $code, $loggingInfo = null)
    {
        if ($this->enableWarning) {
            printf("\n[WARNING]: %s\n", $code);
        }
    }
}
