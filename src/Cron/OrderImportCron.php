<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Services\OrderService;

class OrderImportCron extends Cron
{
  /**
   * @var OrderService
   */
  public $orderService;

  /**
   * @var LoggerContract
   */
  public $loggerContract;

  /**
   * @var LogSenderService
   */
  public $logSenderService;

  /**
   * OrderImportCron constructor.
   *
   * @param OrderService     $orderService
   * @param LoggerContract   $loggerContract
   * @param LogSenderService $logSenderService
   */
  public function __construct(OrderService $orderService, LoggerContract $loggerContract, LogSenderService $logSenderService)
  {
    $this->orderService = $orderService;
    $this->loggerContract = $loggerContract;
    $this->logSenderService = $logSenderService;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    $externalLogs = pluginApp(ExternalLogs::class);
    try {
      $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronStartedMessage'), ['method' => __METHOD__]);
      $this->orderService->process($externalLogs, 1);
    } finally {
      if (count($externalLogs->getLogs())) {
        $this->logSenderService->execute($externalLogs->getLogs());
      }
      $this->loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), ['method' => __METHOD__]);
    }
  }
}
