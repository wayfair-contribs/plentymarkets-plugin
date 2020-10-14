<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\PlentyMockets\Helpers;

use PHPUnit\Framework\MockObject\MockBuilder;
use PHPUnit\Framework\TestCase;

class PluginAppHelper
{
    private $testCase;

    private $returnMap;

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
        $this->returnMap = [];
    }

    public function willReturn(string $originalClassName, array $constructorArguments, $toBeReturned)
    {
        $key = serialize([[$originalClassName], $constructorArguments]);
        $this->returnMap[$key] = $toBeReturned;
    }

    public function pluginApp(string $originalClassName, $constructorArguments = [])
    {
        $key = serialize([[$originalClassName], $constructorArguments]);
        if (array_key_exists($key, $this->returnMap))
        {
            return $this->returnMap[$key];
        }

        // return a shell of the thing by default
        return (new MockBuilder($this->testCase, $originalClassName))
        ->disableOriginalConstructor()
        ->disableOriginalClone()
        ->disableArgumentCloning()
        ->disallowMockingUnknownTypes()
        ->getMock();
    }
}
