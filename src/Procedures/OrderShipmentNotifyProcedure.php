<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Procedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Models\Order;
use Wayfair\Core\Api\Services\LogSenderService;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\ExternalLogs;
use Wayfair\Services\ShipmentNotificationService;

/**
 * Notify Wayfair API when an order's status changed to shipping stage.
 * Class OrderShipmentNotifyProcedure
 *
 * @package Wayfair\Procedures
 */
class OrderShipmentNotifyProcedure
{

  const LOG_KEY_ASN_ALREADY_SENT = 'shipmentNotificationAlreadySent';
  const LOG_KEY_SENDING_NEW_SHIPMENT_NOTIFICATION = 'shipmentNotificationNeedsToBeSent';
  const LOG_KEY_FINISHED_SENDING_NEW_SHIPMENT_NOTIFICATION = 'finishedShipmentNotification';
  const LOG_KEY_RUN_ORDER_STATUS_CHANGE_EVENT = 'runOrderStatusChangeEvent';

  /**
   * @var ShipmentNotificationService
   */
  private $shipmentNotificationService;
  /**
   * @var StorageInterfaceContract
   */
  private $storageInterfaceContract;
  /**
   * @var LoggerContract
   */
  private $loggerContract;

  /**
   * OrderShipmentNotifyProcedure constructor.
   *
   * @param ShipmentNotificationService $shipmentNotificationService
   * @param StorageInterfaceContract $storageInterfaceContract
   * @param LoggerContract $loggerContract
   */
  public function __construct(
    ShipmentNotificationService $shipmentNotificationService,
    StorageInterfaceContract $storageInterfaceContract,
    LoggerContract $loggerContract
  ) {
    $this->shipmentNotificationService = $shipmentNotificationService;
    $this->storageInterfaceContract = $storageInterfaceContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Mark a purchase order in Wayfair as shipped based on status condition.
   *
   * @param EventProceduresTriggered $eventProceduresTriggered
   *
   * @return void
   * @throws \Exception
   */
  public function run(EventProceduresTriggered $eventProceduresTriggered)
  {

    /** @var ExternalLogs $externalLogs */
    $externalLogs = pluginApp(ExternalLogs::class);

    $order_id = -1;
    try {
      /** @var Order $order */
      $order = $eventProceduresTriggered->getOrder();
      if ($order) {
        $order_id = $order->id;
      }

      $this->loggerContract->info(
        TranslationHelper::getLoggerKey(self::LOG_KEY_RUN_ORDER_STATUS_CHANGE_EVENT), [
          'additionalInfo' => ['order' => $order],
          'method' => __METHOD__
        ]
      );

      if ($this->shipmentNotificationService->isOrderSentASN($order)) {
        // we believe the ASN was already sent - another will NOT be sent.
        $this->loggerContract->info(
          TranslationHelper::getLoggerKey(self::LOG_KEY_ASN_ALREADY_SENT), [
            'additionalInfo' => ['order' => $order],
            'method' => __METHOD__
          ]
        );

        $externalLogs->addInfoLog("ASN already sent for order with ID " . $order->id . " so another will NOT be sent.");
      } else {
        $this->loggerContract->debug(
          TranslationHelper::getLoggerKey(self::LOG_KEY_SENDING_NEW_SHIPMENT_NOTIFICATION), [
            'additionalInfo' => ['order' => $order],
            'method' => __METHOD__
          ]
        );

        $this->shipmentNotificationService->notifyShipment($order);

        $this->loggerContract->debug(
          TranslationHelper::getLoggerKey(self::LOG_KEY_FINISHED_SENDING_NEW_SHIPMENT_NOTIFICATION), [
            'additionalInfo' => ['order' => $order],
            'method' => __METHOD__
          ]
        );

        $externalLogs->addInfoLog("Sent an ASN for the order with ID " . $order_id);
      }
    } catch (\Exception $e) {
      $externalLogs->addErrorLog("Failed to notify wayfair about shipment for order with ID " .
        $order_id . " " . get_class($e) . " " . $e->getMessage());
    } finally {
      if (count($externalLogs->getLogs())) {
        /** @var LogSenderService $logSenderService */
        $logSenderService = pluginApp(LogSenderService::class);
        $logSenderService->execute($externalLogs->getLogs());
      }
    }
  }
}
