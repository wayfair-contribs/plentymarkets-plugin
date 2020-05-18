<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Helpers;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodRepositoryContract;
use Wayfair\Core\Helpers\AbstractConfigHelper;

class PaymentHelper {

  /**
   * @var PaymentMethodRepositoryContract
   */
  private $paymentMethodRepository;

  /**
   * PrePaymentHelper constructor.
   *
   * @param PaymentMethodRepositoryContract $paymentMethodRepository
   */
  public function __construct(PaymentMethodRepositoryContract $paymentMethodRepository) {
    $this->paymentMethodRepository = $paymentMethodRepository;
  }

  /**
   * @return int
   */
  public function getPaymentMethodId(): int {
    $paymentMethods = $this->paymentMethodRepository->allForPlugin(AbstractConfigHelper::PLUGIN_NAME);
    foreach($paymentMethods as $paymentMethod) {
      return $paymentMethod->id;
    }
    return $this->createPaymentMethodAndGetId(true);
  }

  /**
   * @param bool $skipChecking
   *
   * @return int
   */
  public function createPaymentMethodAndGetId(bool $skipChecking = false): int {
    if (!$skipChecking) {
      $paymentMethodId = $this->getPaymentMethodId();
      if ($paymentMethodId) {
        return $paymentMethodId;
      }
    }
    $paymentMethodData = [
      'pluginKey'  => AbstractConfigHelper::PLUGIN_NAME,
      'paymentKey' => AbstractConfigHelper::PAYMENT_KEY,
      'name' => 'Wayfair Direct Payment',
    ];
    $paymentMethod = $this->paymentMethodRepository->createPaymentMethod($paymentMethodData);
    return $paymentMethod->id;
  }
}