<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Helpers\PaymentHelper;

class CreatePaymentMethod {

  /**
   * @var PaymentHelper $paymentHelper
   */
  private $paymentHelper;

  /**
   * CreatePaymentMethod constructor.
   *
   * @param PaymentHelper $paymentHelper
   */
  public function __construct(PaymentHelper $paymentHelper)
  {
    $this->paymentHelper = $paymentHelper;
  }

  /**
   * @return void
   */
  public function run()
  {
    $this->paymentHelper->createPaymentMethodAndGetId();
  }
}
