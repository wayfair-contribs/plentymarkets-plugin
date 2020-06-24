<?php
/**
 *
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Api\Services;

use Wayfair\Core\Api\APIService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;

/**
 * Service for sending External logs to Wayfair
 *
 * WARNING: do not use this in the APIService module or its dependencies
 */
class LogSenderService extends APIService {

  public function execute(array $logs) {
    /** @var LoggerContract $loggerContract */
    $loggerContract = pluginApp(LoggerContract::class);

    $declareVariables = '';
    $useVariables = '';
    $variables = [];
    try {
      foreach ($logs as $key => $log) {
        if ($key) {
          $declareVariables .= ', ';
          $useVariables     .= ', ';
        }
        $declareVariables            .= '$l' . ($key + 1) . ': ExternalLogInput!';
        $useVariables                .= 'log' . ($key + 1) . ': log(log: $l' . ($key + 1) . ', dryRun: ' . $this->configHelper->getDryRun() . ')';
        $variables['l' . ($key + 1)] = [
            'app'     => ConfigHelper::INTEGRATION_AGENT_NAME,
            'level'   => $log['level'],
            'message' => $log['message'],
            'details' => $log['details'],
            'type'    => $log['logType'] ?: 'OTHER'
        ];
        if ($log['metrics']) {
          $variables['l' . ($key + 1)]['metrics'] = $log['metrics'];
        }
      }
      $query = 'mutation logExternalMessage(' . $declareVariables . ') {'
               . 'externalLog {'
               . $useVariables
               . '}'
               . '}';
      $this->query($query, 'post', $variables);
    }
    catch (\Exception $e)
    {
      // don't let Exceptions leak out of this sender,
      // as they may override more important Exceptions.
      $loggerContract->error(TranslationHelper::getLoggerKey('unableToSendLogsToWayfair'), [
          'additionalInfo' => ['message' => $e->getMessage()],
          'method'         => __METHOD__
      ]);
    }
  }
}
