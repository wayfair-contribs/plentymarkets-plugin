<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Migrations;

use Wayfair\Helpers\ConfigHelper;
use Wayfair\Repositories\KeyValueRepository;

class CreateOrderImportDate {

  /**
   * @var KeyValueRepository
   */
  private $keyValueRepository;

  /**
   * @param KeyValueRepository $keyValueRepository
   */
  public function __construct(KeyValueRepository $keyValueRepository)
  {
    $this->keyValueRepository = $keyValueRepository;
  }

  /**
   * @throws \Plenty\Exceptions\ValidationException
   */
  public function run()
  {
    if (!$this->keyValueRepository->get(ConfigHelper::IMPORT_ORDER_SINCE)) {
      $this->keyValueRepository->putOrReplace(ConfigHelper::IMPORT_ORDER_SINCE, date('Y-m-d'));
    }
  }

}
