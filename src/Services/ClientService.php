<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Http\WayfairResponse;

class ClientService implements ClientInterfaceContract {

  /**
   * @var LibraryCallContract
   */
  public $library;

  /**
   * @param LibraryCallContract $libraryCallContract
   */
  public function __construct(LibraryCallContract $libraryCallContract) {
    $this->library = $libraryCallContract;
  }

  /**
   * @param string $method
   * @param array  $arguments
   *
   * @return WayfairResponse
   */
  public function call($method, $arguments) {
    $response = $this->library->call(
        ConfigHelper::PLUGIN_NAME . '::guzzle',
        [
            'method'    => $method,
            'arguments' => $arguments
        ]
    );

    return pluginApp(WayfairResponse::class, [$response]);
  }
}
