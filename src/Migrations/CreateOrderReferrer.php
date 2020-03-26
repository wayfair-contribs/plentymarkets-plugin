<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Plenty\Modules\Order\Referrer\Contracts\OrderReferrerRepositoryContract;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Repositories\KeyValueRepository;

class CreateOrderReferrer {

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository) {
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * @param OrderReferrerRepositoryContract $orderReferrerRepository
   *
   * @throws \Plenty\Exceptions\ValidationException
   * @return void
   */
  public function run(OrderReferrerRepositoryContract $orderReferrerRepository) {
    $orderReferrer = $orderReferrerRepository->create(
        [
            'isEditable' => false,
            'backendName' => 'Wayfair',
            'name' => 'Wayfair',
            'origin' => 'Wayfair',
        ]
    );
    $this->keyValueRepository->putOrReplace(ConfigHelper::SETTINGS_ORDER_REFERRER_KEY, $orderReferrer->id);
  }

}