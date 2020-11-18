<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Modules\Frontend\Services\AccountService;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Wayfair\Core\Dto\Constants;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\OrderASN;

/**
 * Class OrderASNRepository
 *
 * @package Wayfair\Repositories
 */
class OrderASNRepository extends Repository {

  const LOG_KEY_QUERY_FAILED = 'asnQueryFailed';

  /** @var DataBase $database */
  private $database;

  /**
   * OrderASNRepository constructor.
   *
   * @param AccountService $accountService
   */
  public function __construct(AccountService $accountService) {
    parent::__construct($accountService);
    /** @var DataBase database */
    $this->database = pluginApp(DataBase::class);
  }

  /**
   * Find an order ASN sending log using order id.
   *
   * @param int $orderId
   *
   * @return OrderASN|null
   */
  public function findByOrderId(int $orderId) {
    $model = [];

    try
    {
      $model = $this->database->query(OrderASN::class)
          ->where('orderId', '=', $orderId)
          ->get();
    }
    catch (\Exception $e) {
      $this->getLogger()
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'referenceType' => 'orderId',
            'referenceValue' => $orderId,
            'method' => __METHOD__
          ]
        );
    }

    return !empty($model) ? $model[0] : null;
  }

  /**
   * Create or update an ASN sending history record.
   *
   * @param $data
   *
   * @return OrderASN|null
   */
  public function createOrUpdate($data) {
    /**
     * @var getLogger() $getLogger()
     */
    $this->getLogger()
        ->info(
            TranslationHelper::getLoggerKey('addOrderToSentASNList'), [
            'additionalInfo' => ['order' => $data],
            'method' => __METHOD__
            ]
        );

    if ($data['orderId']) {
      return null;
    }
    $orderId = $data['orderId'];
    $model = $this->findByOrderId($orderId);
    if (empty($model)) {
      /** @var OrderASN $model */
      $model = pluginApp(OrderASN::class);
      $model->orderId = $orderId;
    }

    //Just update the record with latest datetime.
    $model->createdAt = time();
    $model->type = Constants::SUPPLIER_SHIPPED_ASN;

    $this->database->save($model);

    return $model;
  }
}
