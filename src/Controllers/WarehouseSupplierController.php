<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */
namespace Wayfair\Controllers;

use Plenty\Plugin\Http\Request;
use Wayfair\Repositories\WarehouseSupplierRepository;
use Plenty\Exceptions\ValidationException;
use Plenty\Plugin\Controller;

class WarehouseSupplierController extends Controller {

  /**
   * @var WarehouseSupplierRepository
   */
  private $warehouseSupplierRepository;

  /**
   * WarehouseSupplierController constructor.
   *
   * @param WarehouseSupplierRepository $warehouseSupplierRepository
   */
  public function __construct(WarehouseSupplierRepository $warehouseSupplierRepository) {
    parent::__construct();
    $this->warehouseSupplierRepository = $warehouseSupplierRepository;
  }

  /**
   * Create multiple mapping based upon the input passed.
   *
   * @param Request $request
   *
   * @return false|string
   */
  public function saveMappings(Request $request) {
    $data = $request->input('data');
    try {
      if (is_array($data[0])) {
        return json_encode($this->warehouseSupplierRepository->saveMappings($data));
      }
      return json_encode(['error' => 'Incorrect input format.']);
    } catch (ValidationException $e) {
      return json_encode(['error' => $e->getMessage()]);
    }
  }


  /**
   * @return false|string
   */
  public function getMappings() {
    return json_encode($this->warehouseSupplierRepository->getAllMappings());
  }
}
