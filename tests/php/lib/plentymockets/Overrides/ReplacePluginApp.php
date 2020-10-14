<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

function pluginApp($abstract, $params = [])
{
    global $mockPluginApp;

    return $mockPluginApp->pluginApp($abstract, $params);
}
