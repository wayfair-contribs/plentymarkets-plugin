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

class OrderImportCron extends Cron {

  const LOG_PM_KEY_IMPORT_CORN_JOB_FAILED = 'cronFailedMessage';
  const LOG_PM_KEY_IMPORT_CORN_JOB_STARTED = 'cronStartedMessage';
  const LOG_PM_KEY_IMPORT_CORN_JOB_FINISHED = 'cronFinishedMessage';
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
  public function __construct(OrderService $orderService, LoggerContract $loggerContract, LogSenderService $logSenderService) {
    $this->orderService = $orderService;
    $this->loggerContract = $loggerContract;
    $this->logSenderService = $logSenderService;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function handle() {
    /** @var ExternalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);
    try {
      $this->loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_PM_KEY_IMPORT_CORN_JOB_STARTED), ['method' => __METHOD__]);
      $this->orderService->process($externalLogs, 1);
    }
    catch(\Exception $e) {
      $this->loggerContract->error(TranslationHelper::getLoggerKey(self::LOG_PM_KEY_IMPORT_CORN_JOB_FAILED), ['additionalInfo'=> $this->orderService->process($externalLogs, 1) ,'message' => $e->message, 'method' => __METHOD__]);
      }
    finally {
      if (count($externalLogs->getLogs())) {
        $this->logSenderService->execute($externalLogs->getLogs());
      }
      $this->loggerContract->debug(TranslationHelper::getLoggerKey(self::LOG_PM_KEY_IMPORT_CORN_JOB_FINISHED), ['method' => __METHOD__]);
    }
  }
}
