<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Cron;

use Plenty\Modules\Cron\Contracts\CronHandler as Cron;
use Wayfair\Services\SendLogsService;

class SendLogsCron extends Cron {

  /**
   * @var SendLogsService
   */
  public $sendLogsService;

  /**
   * OrderImportCron constructor.
   *
   * @param SendLogsService $sendLogsService
   */
  public function __construct(SendLogsService $sendLogsService)
  {
    $this->sendLogsService = $sendLogsService;
  }

  /**
   * @throws \Exception
   *
   * @return void
   */
  public function handle()
  {
    $this->sendLogsService->process();
  }
}
