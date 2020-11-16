<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Services;

use Exception;
use Plenty\Modules\Cloud\Storage\Models\StorageObject;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Plugin\Storage\Contracts\StorageRepositoryContract;
use Wayfair\Core\Contracts\FetchDocumentContract;
use Wayfair\Core\Contracts\LoggerContract;
use Wayfair\Core\Dto\RegisterPurchaseOrder\ResponseDTO;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;

/**
 * Class SaveOrderDocumentService
 *
 * @package Wayfair\Services
 */
class SaveOrderDocumentService
{

  const CANNOT_GET_SHIPPING_LABEL = "Cannot get shipping label";

  /**
   * @var StorageRepositoryContract
   */
  private $storageRepositoryContract;

  /**
   * @var OrderRepositoryContract
   */
  private $orderRepositoryContract;

  /**
   * @var FetchDocumentContract
   */
  private $fetchShippingLabelContract;
  /**
   * @var LoggerContract
   */
  private $loggerContract;


  /**
   * SaveOrderDocumentService constructor.
   *
   * @param StorageRepositoryContract $storageRepositoryContract
   * @param OrderRepositoryContract $orderRepositoryContract
   * @param FetchDocumentContract $fetchShippingLabelContract
   * @param LoggerContract $loggerContract
   */
  public function __construct(
    StorageRepositoryContract $storageRepositoryContract,
    OrderRepositoryContract $orderRepositoryContract,
    FetchDocumentContract $fetchShippingLabelContract,
    LoggerContract $loggerContract
  )
  {
    $this->storageRepositoryContract = $storageRepositoryContract;
    $this->orderRepositoryContract = $orderRepositoryContract;
    $this->fetchShippingLabelContract = $fetchShippingLabelContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Save a document with array of data for order.
   *
   * @param string $fileName
   * @param string $fileData
   *
   * @return StorageObject
   */
  public function save(string $fileName, string $fileData): StorageObject
  {
    $this->loggerContract
      ->info(
        TranslationHelper::getLoggerKey('saveFileSThree'), [
          'additionalInfo' => ['key' => $fileName, 'data' => base64_encode($fileData)],
          'method' => __METHOD__
        ]
      );

    return $this->storageRepositoryContract->uploadObject(
      AbstractConfigHelper::PLUGIN_NAME, $fileName, $fileData
    );
  }

  /**
   * Get PM native orderId from WF poNumber, throw exception if not found.
   *
   * @param string $poNumber
   *
   * @return mixed
   * @throws \Exception
   */
  private function getCheckedOrderId(string $poNumber)
  {
    if (!isset($poNumber) or empty($poNumber)) {
      throw new \Exception("Cannot check for Plentymarkets order - no PO Number provided.");
    }

    $orderId = null;

    $plentyOrder = $this->orderRepositoryContract->findOrderByExternalOrderId($poNumber);

    if (isset($plentyOrder))
    {
      $orderId = $plentyOrder->id;
    }

    if (isset($orderId))
    {
      return $orderId;
    }

    throw new \Exception('Plentymarkets Order does not exist for Wayfair poNumber: ' . $poNumber);
  }
}
