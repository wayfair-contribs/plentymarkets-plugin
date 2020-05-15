<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Modules\Frontend\Services\AccountService;
use Wayfair\Core\Contracts\LoggerContract;

class Repository
{
  /**
   * @var AccountService
   */
  private $accountService;

  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * WarehouseSupplierRepository constructor.
   *
   * @param AccountService $accountService
   */
  public function __construct(AccountService $accountService)
  {
    $this->accountService = $accountService;
    $this->loggerContract = pluginApp(LoggerContract::class);
  }

  /**
   * @return int
   */
  public function getLoggedInUserId()
  {
    return $this->accountService->getAccountContactId();
  }
}
