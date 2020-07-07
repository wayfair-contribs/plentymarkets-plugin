<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Models;

use Wayfair\Core\Helpers\TimeHelper;

class ExternalLogs {

  /**
   * @var array
   */
  private $logs = [];

  /**
   * @return array
   */
  public function getLogs(): array {
    return $this->logs;
  }

  /**
   * @return void
   */
  public function clearLogs() {
    $this->logs = [];
  }

  /**
   * @param array $log
   *
   * @return void
   */
  public function addLog(array $log) {
    $this->logs[] = $log;
  }

  /**
   * @param string $message
   * @param string $details
   *
   * @return void
   */
  public function addErrorLog(string $message, string $details = null) {
    $this->addCustomLog('ERROR', $message, $details);
  }

  /**
   * @param string $message
   * @param string $details
   *
   * @return void
   */
  public function addWarningLog(string $message, string $details = null) {
    $this->addCustomLog('WARNING', $message, $details);
  }

  /**
   * @param string $message
   * @param string $details
   *
   * @return void
   */
  public function addInfoLog(string $message, string $details = null) {
    $this->addCustomLog('INFO', $message, $details);
  }

  /**
   * @param string $message
   * @param string $details
   *
   * @return void
   */
  public function addDebugLog(string $message, string $details = null) {
    $this->addCustomLog('DEBUG', $message, $details);
  }

  /**
   * @param string $message
   * @param string $type
   * @param int    $cnt
   * @param float  $duration
   * @param bool   $applyDuration
   *
   * @return void
   */
  public function addPurchaseOrderLog(string $message, string $type, int $cnt, float $duration, bool $applyDuration = true) {
    $this->addLogWithMetrics('PURCHASE_ORDER', $message, $type, $cnt, $duration, $applyDuration);
  }

  /**
   * @param string $message
   * @param string $type
   * @param int    $cnt
   * @param float  $duration
   * @param bool   $applyDuration
   *
   * @return void
   */
  public function addInventoryLog(string $message, string $type, int $cnt, float $duration, bool $applyDuration = true) {
    $this->addLogWithMetrics('INVENTORY', $message, $type, $cnt, $duration, $applyDuration);
  }

  /**
   * @param string $message
   * @param string $type
   * @param int    $cnt
   * @param float  $duration
   * @param bool   $applyDuration
   *
   * @return void
   */
  public function addShippingLabelLog(string $message, string $type, int $cnt, float $duration, bool $applyDuration = true) {
    $this->addLogWithMetrics('SHIPPING_LABEL', $message, $type, $cnt, $duration, $applyDuration);
  }

  /**
   * @param string $message
   * @param string $type
   * @param int    $cnt
   * @param float  $duration
   * @param bool   $applyDuration
   *
   * @return void
   */
  public function addASNLog(string $message, string $type, int $cnt, float $duration, bool $applyDuration = true) {
    $this->addLogWithMetrics('ASN', $message, $type, $cnt, $duration, $applyDuration);
  }

  /**
   * @param string $logType
   * @param string $message
   * @param string $type
   * @param int    $cnt
   * @param float  $duration
   * @param bool   $applyDuration
   *
   * @return void
   */
  private function addLogWithMetrics(string $logType, string $message, string $type, int $cnt, float $duration, bool $applyDuration) {
    $metrics = [
      'type' => $type,
      'value' => $cnt,
    ];
    if ($applyDuration) {
      $metrics['duration'] = [
        'value' => $duration,
        'unit' => 'MILLISECONDS'
      ];
    }
    $this->logs[] = [
      'message' => $message,
      'level' => 'INFO',
      'logType' => $logType,
      'metrics' => [
        $metrics
      ]
    ];
  }

  /**
   * @param string $level
   * @param string $message
   */
  private function addCustomLog(string $level, string $message, $details = null) {
    $log = [
      'message' => $message,
      'level' => $level,
      'logType' => 'OTHER',
      'time_stamp' => TimeHelper::getMilliseconds()
    ];

    if (isset($details))
    {
      $log['details'] = $details;
    }
  }
}
