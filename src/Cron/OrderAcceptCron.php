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

class OrderAcceptCron extends Cron
{

  /**
   * @var OrderService
   */
  public $orderService;

  /**
   * @var LogSenderService
   */
  public $logSenderService;

  /**
   * OrderImportCron constructor.
   *
   * @param OrderService     $orderService
   * @param LogSenderService $logSenderService
   */
  public function __construct(OrderService $orderService, LogSenderService $logSenderService)
  {
    $this->orderService = $orderService;
    $this->logSenderService = $logSenderService;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    /** @var ExternalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    /**
     * @var LoggerContract $loggerContract
     */
    $loggerContract = pluginApp(LoggerContract::class);
    $loggerContract->debug(TranslationHelper::getLoggerKey('cronStartedMessage'), ['method' => __METHOD__]);
    try {
      $this->orderService->accept($externalLogs, 1);
    } finally {
      if (count($externalLogs->getLogs())) {
        $this->logSenderService->execute($externalLogs->getLogs());
      }
      $loggerContract->debug(TranslationHelper::getLoggerKey('cronFinishedMessage'), ['method' => __METHOD__]);
    }
  }
}
