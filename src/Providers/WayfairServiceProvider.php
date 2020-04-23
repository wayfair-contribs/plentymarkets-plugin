<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Providers;

use Plenty\Log\Services\ReferenceContainer;
use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Order\Shipping\ServiceProvider\Services\ShippingServiceProviderService;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\ServiceProvider;
use Wayfair\Core\Api\Services\AuthService;
use Wayfair\Core\Api\Services\FetchDocumentService;
use Wayfair\Core\Api\Services\RegisterPurchaseOrderService;
use Wayfair\Core\Contracts\AuthenticationContract;
use Wayfair\Core\Contracts\ClientInterfaceContract;
use Wayfair\Core\Contracts\ConfigHelperContract;
use Wayfair\Core\Contracts\FetchDocumentContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Contracts\RegisterPurchaseOrderContract;
use Wayfair\Core\Contracts\StorageInterfaceContract;
use Wayfair\Core\Contracts\URLHelperContract;
use Wayfair\Core\Helpers\URLHelper;
use Wayfair\Cron\InventoryFullCron;
use Wayfair\Cron\InventorySyncCron;
use Wayfair\Cron\OrderAcceptCron;
use Wayfair\Cron\UpdateFullInventoryStatusCron;
use Wayfair\Helpers\ConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Procedures\OrderShipmentNotifyProcedure;
use Wayfair\Services\ClientService;
use Wayfair\Services\LoggingService;
use Wayfair\Services\StorageService;
use Wayfair\Cron\OrderImportCron;

/**
 * Class WayfairServiceProvider
 *
 * @package Wayfair\Providers
 */
class WayfairServiceProvider extends ServiceProvider
{
  use Loggable;


  /**
   * Register the service provider.
   *
   * @return void
   */
  public function register()
  {
    $this->getApplication()->register(WayfairRouteServiceProvider::class);
    $this->getApplication()->bind(ClientInterfaceContract::class, ClientService::class);
    $this->getApplication()->bind(AuthenticationContract::class, AuthService::class);
    $this->getApplication()->bind(StorageInterfaceContract::class, StorageService::class);
    $this->getApplication()->singleton(ConfigHelperContract::class, ConfigHelper::class);
    $this->getApplication()->bind(RegisterPurchaseOrderContract::class, RegisterPurchaseOrderService::class);
    $this->getApplication()->bind(FetchDocumentContract::class, FetchDocumentService::class);
    $this->getApplication()->bind(LoggerContract::class, LoggingService::class);
    $this->getApplication()->singleton(URLHelperContract::class, URLHelper::class);
  }

  /**
   * @param CronContainer                  $cronContainer
   * @param ShippingServiceProviderService $shippingServiceProviderService
   * @param ReferenceContainer             $referenceContainer
   * @param EventProceduresService         $eventProceduresService
   *
   * @return null
   */
  public function boot(
    CronContainer $cronContainer,
    ShippingServiceProviderService $shippingServiceProviderService,
    ReferenceContainer $referenceContainer,
    EventProceduresService $eventProceduresService
  ) {

    try {
      // register crons
      $cronContainer->add(CronContainer::EVERY_FIFTEEN_MINUTES, OrderImportCron::class);
      $cronContainer->add(CronContainer::EVERY_FIFTEEN_MINUTES, InventorySyncCron::class);
      $cronContainer->add(CronContainer::EVERY_FIFTEEN_MINUTES, OrderAcceptCron::class);
      $cronContainer->add(CronContainer::DAILY, InventoryFullCron::class);
      $cronContainer->add(CronContainer::HOURLY, UpdateFullInventoryStatusCron::class);

      $shippingControllers = [
        'Wayfair\\Controllers\\ShippingController@registerShipments',
        'Wayfair\\Controllers\\ShippingController@getLabels',
        'Wayfair\\Controllers\\ShippingController@deleteShipments'
      ];
      $shippingServiceProviderService->registerShippingProvider(
        ConfigHelper::PLUGIN_NAME,
        ['de' => ConfigHelper::SHIPPING_PROVIDER_NAME, 'en' => ConfigHelper::SHIPPING_PROVIDER_NAME],
        $shippingControllers
      );

      //Register ASN sending procedure.
      $eventProceduresService->registerProcedure(
        'sendWayfairASN',
        ProcedureEntry::EVENT_TYPE_ORDER,
        [
          'de' => 'Versandsbestätigung (ASN) an Wayfair senden',
          'en' => 'Send Ship Confirmation (ASN) to Wayfair'
        ],
        OrderShipmentNotifyProcedure::class . '@run'
      );

      try {
        $referenceContainer->add(
          [
            'poNumber'   => 'poNumber',
            'orderId'    => 'orderId',
            'statusCode' => 'statusCode',
          ]
        );
      } catch (\Exception $e) {
        $this->getLogger(__METHOD__)->error(
          TranslationHelper::getLoggerKey('errorAddingReferenceContainer'),
          [
            'poNumber'   => 'poNumber',
            'orderId'    => 'orderId',
            'statusCode' => 'statusCode',
            'message'    => $e->getMessage()
          ]
        );
      }
    } finally {
      ConfigHelper::setBootFlag();
    }
  }
}
