<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\RegisterPurchaseOrder;

use Wayfair\Core\Dto\General\BillOfLadingDTO;
use Wayfair\Core\Dto\General\ShippingLabelDTO;
use Wayfair\Core\Dto\General\GeneratedShippingLabelDTO;

class ResponseDTO {

  /**
   * @var string
   */
  private $id;

  /**
   * @var string
   */
  private $eventDate;

  /**
   * @var string
   */
  private $pickupDate;

  /**
   * @var int
   */
  private $poNumber;

  /**
   * @var BillOfLadingDTO
   */
  private $billOfLading;

  /**
   * @var ShippingLabelDTO
   */
  private $consolidatedShippingLabel;

  /**
   * @var GeneratedShippingLabelDTO[]
   */
  private $generatedShippingLabels;

  /**
   * @var \Wayfair\Core\Dto\PurchaseOrder\ResponseDTO
   */
  private $purchaseOrder;

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param mixed $id
   *
   * @return void
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getEventDate()
  {
    return $this->eventDate;
  }

  /**
   * @param mixed $eventDate
   *
   * @return void
   */
  public function setEventDate($eventDate)
  {
    $this->eventDate = $eventDate;
  }

  /**
   * @return string
   */
  public function getPickupDate()
  {
    return $this->pickupDate;
  }

  /**
   * @param mixed $pickupDate
   *
   * @return void
   */
  public function setPickupDate($pickupDate)
  {
    $this->pickupDate = $pickupDate;
  }

  /**
   * @return int
   */
  public function getPoNumber()
  {
    return $this->poNumber;
  }

  /**
   * @param mixed $poNumber
   *
   * @return void
   */
  public function setPoNumber($poNumber)
  {
    $this->poNumber = $poNumber;
  }

  /**
   * @return BillOfLadingDTO
   */
  public function getBillOfLading()
  {
    return $this->billOfLading;
  }

  /**
   * @param mixed $billOfLading
   *
   * @return void
   */
  public function setBillOfLading($billOfLading)
  {
    $this->billOfLading = BillOfLadingDTO::createFromArray($billOfLading);
  }

  /**
   * @return ShippingLabelDTO
   */
  public function getConsolidatedShippingLabel()
  {
    return $this->consolidatedShippingLabel;
  }

  /**
   * @param mixed $consolidatedShippingLabel
   *
   * @return void
   */
  public function setConsolidatedShippingLabel($consolidatedShippingLabel)
  {
    $this->consolidatedShippingLabel = ShippingLabelDTO::createFromArray($consolidatedShippingLabel);
    ;
  }

  /**
   * @return array
   */
  public function getGeneratedShippingLabels()
  {
    return $this->generatedShippingLabels;
  }

  /**
   * @param mixed $generatedShippingLabels
   *
   * @return void
   */
  public function setGeneratedShippingLabels($generatedShippingLabels)
  {
    $this->generatedShippingLabels = [];
    foreach ($generatedShippingLabels as $generatedShippingLabel) {
      $this->generatedShippingLabels[] = GeneratedShippingLabelDTO::createFromArray($generatedShippingLabel);
    }
  }

  /**
   * @return \Wayfair\Core\Dto\PurchaseOrder\ResponseDTO
   */
  public function getPurchaseOrder(): \Wayfair\Core\Dto\PurchaseOrder\ResponseDTO
  {
    return $this->purchaseOrder;
  }

  /**
   * @param mixed $purchaseOrder
   *
   * @return void
   */
  public function setPurchaseOrder($purchaseOrder)
  {
    $this->purchaseOrder = \Wayfair\Core\Dto\PurchaseOrder\ResponseDTO::createFromArray($purchaseOrder);
  }

  /**
   * Static function to create a new Response DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    /**
     * @var ResponseDTO $dto
     */
    $dto = pluginApp(ResponseDTO::class);
    $dto->setId($params['id'] ?? null);
    $dto->setEventDate($params['eventDate'] ?? null);
    $dto->setPickupDate($params['pickupDate'] ?? null);
    $dto->setPoNumber($params['poNumber'] ?? null);
    $dto->setBillOfLading($params['billOfLading'] ?? null);
    $dto->setConsolidatedShippingLabel($params['consolidatedShippingLabel'] ?? null);
    $dto->setGeneratedShippingLabels($params['generatedShippingLabels'] ?? null);
    $dto->setPurchaseOrder($params['purchaseOrder'] ?? null);
    return $dto;
  }
}
