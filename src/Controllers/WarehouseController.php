<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */
namespace Wayfair\Controllers;

use Plenty\Modules\StockManagement\Warehouse\Contracts\WarehouseRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Templates\Twig;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;

/**
 * Class WarehouseController
 *
 * @package Wayfair\Controllers
 */
class WarehouseController extends Controller {

  const LOG_KEY_CONTROLLER_IN = "controllerInput";
  const LOG_KEY_CONTROLLER_OUT = "controllerOutput";

  /**
   * @var LoggerContract
   */
  private $logger;

   /**
   * WarehouseController constructor.
   *
   * @param LoggerContract $logger
   */
  public function __construct(LoggerContract $logger)
  {
    $this->logger = $logger;
  }

  /**
   * @param Twig $twig
   *
   * @return string
   */
  public function index(Twig $twig): string {
    $warehouses = '{a:1}';

    $data = $twig->render('Wayfair::content.warehouse', ['warehouses' => $warehouses]);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $data],
      'method'         => __METHOD__
    ]);

    return $data;
  }

  /**
   * @param Twig                        $twig
   * @param WarehouseRepositoryContract $warehouseRepositoryContract
   *
   * @return string
   */
  public function show(Twig $twig, WarehouseRepositoryContract $warehouseRepositoryContract): string {
    $warehouses = json_encode($warehouseRepositoryContract->all());

    $data = $twig->render('Wayfair::content.warehouse', ['warehouses' => $warehouses]);

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $data],
      'method'         => __METHOD__
    ]);

    return $data;
  }

  /**
   * @param WarehouseRepositoryContract $warehouseRepositoryContract
   *
   * @return string
   */
  public function fetch(WarehouseRepositoryContract $warehouseRepositoryContract): string {

    $data = json_encode($warehouseRepositoryContract->all());

    $this->logger->debug(TranslationHelper::getLoggerKey(self::LOG_KEY_CONTROLLER_OUT), [
      'additionalInfo' => ['payloadOut' => $data],
      'method'         => __METHOD__
    ]);

    return $data;
  }
}
