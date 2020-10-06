<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Wayfair\Core\Contracts\LoggerContract;

class Repository
{
  /**
   * @var LoggerContract
   */
  protected $loggerContract;

  /**
   * @var DataBase
   */
  protected $database;

  /**
   * Repository constructor.
   *
   * @param DataBase $database
   * @param LoggerContract $loggerContract
   */
  public function __construct(DataBase $database, LoggerContract $loggerContract)
  {
    $this->loggerContract = $loggerContract;
    $this->database = $database;
  }
}
