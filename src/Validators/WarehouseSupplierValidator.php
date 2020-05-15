<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Validators;

use Plenty\Validation\Validator;

class WarehouseSupplierValidator extends Validator {

  /**
   * @return array
   */
  public function buildCustomMessages()
  {
    // @FIXME Implementation of this method is unknown. Verify what needs to be done in this function.
    return [
      'supplierId' => 'The :attribute is required',
      'warehouseId' => 'The :attribute is required'
    ];
  }

  /**
   * Method to fux the validation rules.
   *
   * @return null
   */
  protected function defineAttributes()
  {
    $this->addString('supplierId', true);
    $this->addString('warehouseId', true);
  }
}
