<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Repositories\PendingLogsRepository;

class SendLogsService
{

  /**
   * @var PendingLogsRepository
   */
  private $pendingLogsRepository;

  /**
   * @var LogSenderService
   */
  private $logSenderService;

  /**
   * OrderService constructor.
   *
   * @param PendingLogsRepository $pendingLogsRepository
   * @param LogSenderService      $logSenderService
   */
  public function __construct(PendingLogsRepository $pendingLogsRepository, LogSenderService $logSenderService)
  {
    $this->pendingLogsRepository = $pendingLogsRepository;
    $this->logSenderService = $logSenderService;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function process()
  {
    $logs = $this->pendingLogsRepository->getAll();
    if (count($logs)) {
      $ids = $this->logSenderService->execute($logs);
      $this->pendingLogsRepository->delete($ids);
    }
  }
}
