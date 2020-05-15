<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\Inventory;

class RequestDTO
{
  /**
   * @var int
   */
  private $supplierId;

  /**
   * @var string
   */
  private $supplierPartNumber;

  /**
   * @var int|null
   */
  private $quantityOnHand;

  /**
   * @var int|null
   */
  private $quantityBackorder;

  /**
   * @var int|null
   */
  private $quantityOnOrder;

  /**
   * @var string
   */
  private $itemNextAvailabilityDate;

  /**
   * @var string
   */
  private $productNameAndOptions;

  /**
   * @var bool
   */
  private $discontinued;

  /**
   * @return int
   */
  public function getSupplierId()
  {
    return $this->supplierId;
  }

  /**
   * @param int|null $supplierId
   *
   * @return void
   */
  public function setSupplierId($supplierId)
  {
    $this->supplierId = $supplierId;
  }

  /**
   * @return string
   */
  public function getSupplierPartNumber()
  {
    return $this->supplierPartNumber;
  }

  /**
   * @param string $supplierPartNumber
   *
   * @return void
   */
  public function setSupplierPartNumber($supplierPartNumber)
  {
    $this->supplierPartNumber = $supplierPartNumber;
  }

  /**
   * @return int|null
   */
  public function getQuantityOnHand()
  {
    return $this->quantityOnHand;
  }

  /**
   * @param int|null $quantityOnHand
   *
   * @return void
   */
  public function setQuantityOnHand($quantityOnHand)
  {
    $this->quantityOnHand = $quantityOnHand;
  }

  /**
   * @return int|null
   */
  public function getQuantityBackorder()
  {
    return $this->quantityBackorder;
  }

  /**
   * @param int|null $quantityBackorder
   *
   * @return void
   */
  public function setQuantityBackorder($quantityBackorder)
  {
    $this->quantityBackorder = $quantityBackorder;
  }

  /**
   * @return int|null
   */
  public function getQuantityOnOrder()
  {
    return $this->quantityOnOrder;
  }

  /**
   * @param int|null $quantityOnOrder
   *
   * @return void
   */
  public function setQuantityOnOrder($quantityOnOrder)
  {
    $this->quantityOnOrder = $quantityOnOrder;
  }

  /**
   * @return string
   */
  public function getItemNextAvailabilityDate()
  {
    return $this->itemNextAvailabilityDate;
  }

  /**
   * @param string $itemNextAvailabilityDate
   *
   * @return void
   */
  public function setItemNextAvailabilityDate($itemNextAvailabilityDate)
  {
    $this->itemNextAvailabilityDate = $itemNextAvailabilityDate;
  }

  /**
   * @return string
   */
  public function getProductNameAndOptions()
  {
    return $this->productNameAndOptions;
  }

  /**
   * @param string $productNameAndOptions
   *
   * @return void
   */
  public function setProductNameAndOptions($productNameAndOptions)
  {
    $this->productNameAndOptions = $productNameAndOptions;
  }

  /**
   * @return bool
   */
  public function isDiscontinued()
  {
    return $this->discontinued;
  }

  /**
   * @param bool $discontinued
   *
   * @return void
   */
  public function setDiscontinued($discontinued)
  {
    $this->discontinued = $discontinued;
  }

  /**
   * Static function to create a new RequestDTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    /**
     * @var RequestDTO $dto
     */
    $dto = pluginApp(RequestDTO::class);
    $dto->setSupplierId($params['supplierId'] ?? null);
    $dto->setSupplierPartNumber($params['supplierPartNumber'] ?? null);
    $dto->setQuantityOnHand($params['quantityOnHand'] ?? null);
    $dto->setQuantityBackorder($params['quantityBackorder'] ?? null);
    $dto->setQuantityOnOrder($params['quantityOnOrder'] ?? null);
    $dto->setItemNextAvailabilityDate($params['itemNextAvailabilityDate'] ?? null);
    $dto->setProductNameAndOptions($params['productNameAndOptions'] ?? null);
    $dto->setDiscontinued($params['discontinued'] ?? null);
    return $dto;
  }

  /**
   * @return array
   */
  public function toArray()
  {
    $data = [];
    $data['supplierId'] = $this->getSupplierId();
    $data['supplierPartNumber'] = $this->getSupplierPartNumber();
    $data['quantityOnHand'] = $this->getQuantityOnHand();
    $data['quantityBackorder'] = $this->getQuantityBackorder();
    $data['quantityOnOrder'] = $this->getQuantityOnOrder();
    $data['itemNextAvailabilityDate'] = $this->getItemNextAvailabilityDate();
    $data['productNameAndOptions'] = $this->getProductNameAndOptions();
    $data['discontinued'] = $this->isDiscontinued();
    return $data;
  }
}
