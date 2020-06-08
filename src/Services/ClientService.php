<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Plenty\Modules\Plugin\Libs\Contracts\LibraryCallContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Http\WayfairResponse;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Core\Contracts\LoggerContract;

class ClientService implements ClientInterfaceContract {

  const LOG_KEY_INVENTORY_QUERY_DEBUG = 'Test';

  /**
   * @var LibraryCallContract
   */
  public $library;

  /**
   * Undocumented variable
   *
   * @var LoggerContract
   */
  public $loggerContract;

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
    $loggerContract = pluginApp(LoggerContract::class);
    $response = $this->library->call(
        ConfigHelper::PLUGIN_NAME . '::guzzle',
        [
            'method'    => $method,
            'arguments' => $arguments
        ]
    );

    $loggerContract->debug(
      TranslationHelper::getLoggerKey(self::LOG_KEY_INVENTORY_QUERY_DEBUG),
      [
        'additionalInfo' => [
          'message' => 'Is this in the Client Service',
          'response' => $response
        ],
        'method' => __METHOD__
      ]
    );

    return pluginApp(WayfairResponse::class, [$response]);
  }
}
