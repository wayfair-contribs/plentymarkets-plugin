<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Validators;

use Wayfair\Core\Dto\Constants;
use Plenty\Validation\Validator;

class MeasurementDTOValidator extends Validator {

  /**
   * @return void
   */
  public function buildCustomMessages()
  {
  }

  /**
   * @return void
   */
  protected function defineAttributes()
  {
    $this->addNumeric('value', true);
    $this->addString('unit', true)->in(Constants::AVAILABLE_LENGTH_UNITS);
  }
}
