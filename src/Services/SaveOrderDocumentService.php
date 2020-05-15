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
  ) {
    $this->storageRepositoryContract = $storageRepositoryContract;
    $this->orderRepositoryContract = $orderRepositoryContract;
    $this->fetchShippingLabelContract = $fetchShippingLabelContract;
    $this->loggerContract = $loggerContract;
  }

  /**
   * Save order file data to order
   *
   * @param ResponseDTO $responseDTO
   * @param string $fileName
   *
   * @return StorageObject
   * @throws \Wayfair\Core\Exceptions\TokenNotFoundException
   * @throws \Exception
   */
  public function savePoShippingLabel(ResponseDTO $responseDTO, string $fileName): StorageObject
  {

    if (!isset($responseDTO)) {
      throw new \Exception(self::CANNOT_GET_SHIPPING_LABEL .
        ": No PO registration response  DTO was provided");
    }

    $purchase_order_info = $responseDTO->getPurchaseOrder();
    if (!isset($purchase_order_info)) {
      throw new \Exception(self::CANNOT_GET_SHIPPING_LABEL .
        ": No PO information in DTO");
    }

    $poNumberWithPrefix = $purchase_order_info->getStorePrefix() . $purchase_order_info->getPoNumber();

    $pm_order_id = $this->getCheckedOrderId($poNumberWithPrefix);
    // order validation happens inside getCheckedOrderId

    $shipping_label_info = $responseDTO->getConsolidatedShippingLabel();
    if (!isset($shipping_label_info)) {
      throw new \Exception(self::CANNOT_GET_SHIPPING_LABEL .
        " for PO " . $poNumberWithPrefix . ": No Shipping Label information in DTO." . " Order: " . $pm_order_id);
    }

    $label_url = $shipping_label_info->getUrl();
    if (empty($label_url)) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey('emptyLabelUrl'), [
            'additionalInfo' => [
              'responseDto' => $responseDTO,
              'PO' => $poNumberWithPrefix,
              'order' => $pm_order_id
            ],
            'method' => __METHOD__
          ]
        );

      throw new \Exception(self::CANNOT_GET_SHIPPING_LABEL . " for PO " .
        $poNumberWithPrefix . ": Shipping label URL is empty." . " Order: " . $pm_order_id);
    }

    try {
      $labelFile = $this->fetchShippingLabelContract->fetch($label_url);
    } catch (\Exception $e) {
      throw new \Exception("Shipping label fetch failed : " . $e);
    }

    if (empty($labelFile->getFileContent())) {
      throw new \Exception("Label file content empty, cannot save to PM. Label URL: "  . $label_url);
    }

    return $this->save($fileName, $labelFile->getFileContent());
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

    $this->orderRepositoryContract->setFilters(['externalOrderId' => $poNumber]);
    $orderList = $this->orderRepositoryContract->searchOrders();

    if ($orderList->getTotalCount() >= 1) {
      $result_item = $orderList->getResult();

      if (isset($result_item) && array_key_exists(0, $result_item) && array_key_exists('id', $result_item[0])) {
        return $result_item[0]['id'];
      }
    }

    throw new \Exception('Plentymarkets Order does not exist for Wayfair poNumber: ' . $poNumber);
  }
}
