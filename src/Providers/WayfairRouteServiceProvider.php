<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;

/**
 * Class WayfairRouteServiceProvider
 *
 * @package Wayfair\Providers
 */
class WayfairRouteServiceProvider extends RouteServiceProvider {
  /**
   * @param Router $router
   *
   * @return void
   */
  public function map(Router $router) {
    $router->get(
        'wayfair', [
        'uses'       => 'Wayfair\Controllers\HomeController@index',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/warehouses', [
        'uses'       => 'Wayfair\Controllers\WarehouseController@fetch',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/test', [
        'uses'       => 'Wayfair\Controllers\HomeController@test',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/test/register', [
        'uses' => 'Wayfair\Controllers\PurchaseRegisterController@test',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/inventory', [
        'uses'       => 'Wayfair\Controllers\InventoryController@fetch',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/inventory/filtered', [
        'uses'       => 'Wayfair\Controllers\InventoryController@filtered',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/inventory/filtered1', [
                                        'uses'       => 'Wayfair\Controllers\InventoryController@filtered1',
                                        'middleware' => ['oauth.cookie']
                                    ]
    );
    $router->get(
        'wayfair/inventory/item', [
        'uses'       => 'Wayfair\Controllers\InventoryController@getItem',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/inventory/sync', [
        'uses'       => 'Wayfair\Controllers\InventoryController@sync',
        'middleware' => ['oauth.cookie']
        ]
    );

    // Production URLs

    // Warehouse Supplier Mappings.
    $router->get(
        'wayfair/warehouseSupplier', [
        'uses'       => 'Wayfair\Controllers\WarehouseSupplierController@getMappings',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->post(
        'wayfair/warehouseSupplier', [
        'uses'       => 'Wayfair\Controllers\WarehouseSupplierController@saveMappings',
        'middleware' => ['oauth.cookie']
        ]
    );
    // Full inventory
    $router->post(
        'wayfair/fullInventory', [
        'uses'       => 'Wayfair\Controllers\FullInventoryController@sync',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/fullInventory', [
        'uses'       => 'Wayfair\Controllers\FullInventoryController@getState',
        'middleware' => ['oauth.cookie']
        ]
    );
    //Stock Buffer Mappings
    $router->get(
        'wayfair/settings', [
        'uses'       => 'Wayfair\Controllers\SettingsController@get',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->post(
        'wayfair/settings', [
        'uses'       => 'Wayfair\Controllers\SettingsController@post',
        'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/carriers',
        [
            'uses'       => 'Wayfair\Controllers\CarrierScacController@getCarriers',
            'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/carrierScacs',
        [
            'uses'       => 'Wayfair\Controllers\CarrierScacController@getMapping',
            'middleware' => ['oauth.cookie']
        ]
    );
    $router->post(
        'wayfair/carrierScacs',
        [
            'uses'       => 'Wayfair\Controllers\CarrierScacController@post',
            'middleware' => ['oauth.cookie']
        ]
    );
    $router->get(
        'wayfair/shippingMethod',
        [
            'uses'       => 'Wayfair\Controllers\CarrierScacController@getShippingMethod',
            'middleware' => ['oauth.cookie']
        ]
    );
    $router->post(
        'wayfair/shippingMethod',
        [
            'uses'       => 'Wayfair\Controllers\CarrierScacController@postShippingMethod',
            'middleware' => ['oauth.cookie']
        ]
    );
  }
}
