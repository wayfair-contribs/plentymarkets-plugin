<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */
namespace Wayfair\Controllers;

use Plenty\Exceptions\ValidationException;
use Plenty\Modules\StockManagement\Warehouse\Contracts\WarehouseRepositoryContract;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Templates\Twig;
use Wayfair\Repositories\WarehouseSupplierRepository;

/**
 * Class WarehouseController
 *
 * @package Wayfair\Controllers
 */
class WarehouseController extends Controller {

  /**
   * @param Twig $twig
   *
   * @return string
   */
  public function index(Twig $twig): string {
    $warehouses = '{a:1}';

    return $twig->render('Wayfair::content.warehouse', ['warehouses' => $warehouses]);
  }

  /**
   * @param Twig                        $twig
   * @param WarehouseRepositoryContract $warehouseRepositoryContract
   *
   * @return string
   */
  public function show(Twig $twig, WarehouseRepositoryContract $warehouseRepositoryContract): string {
    $warehouses = json_encode($warehouseRepositoryContract->all());
    return $twig->render('Wayfair::content.warehouse', ['warehouses' => $warehouses]);
  }

  /**
   * @param WarehouseRepositoryContract $warehouseRepositoryContract
   *
   * @return string
   */
  public function fetch(WarehouseRepositoryContract $warehouseRepositoryContract): string {
    return json_encode($warehouseRepositoryContract->all());
  }
}
