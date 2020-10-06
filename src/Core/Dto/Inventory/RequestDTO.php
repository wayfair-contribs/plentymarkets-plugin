<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\Inventory;

class RequestDTO
{

  const KEY_SUPPLIER_ID = 'supplierId';
  const KEY_SUPPLIER_PART_NUMBER = 'supplierPartNumber';
  const KEY_QUANTITY_ON_HAND = 'quantityOnHand';
  const KEY_QUANTITY_BACKORDER = 'quantityBackorder';
  const KEY_QUANTITY_ON_ORDER = 'quantityOnOrder';
  const KEY_ITEM_NEXT_AVAILABILITY_DATE = 'itemNextAvailabilityDate';
  const KEY_PRODUCT_NAME_AND_OPTIONS = 'productNameAndOptions';
  const KEY_DISCONTINUED = 'discontinued';

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
   * Adopt the data in the array into the DTO
   *
   * @param array $params Params
   *
   * @return void
   */
  public function adoptArray(array $params): void
  {
    $this->setSupplierId($params[self::KEY_SUPPLIER_ID] ?? null);
    $this->setSupplierPartNumber($params[self::KEY_SUPPLIER_PART_NUMBER] ?? null);
    $this->setQuantityOnHand($params[self::KEY_QUANTITY_ON_HAND] ?? null);
    $this->setQuantityBackorder($params[self::KEY_QUANTITY_BACKORDER] ?? null);
    $this->setQuantityOnOrder($params[self::KEY_QUANTITY_ON_ORDER] ?? null);
    $this->setItemNextAvailabilityDate($params[self::KEY_ITEM_NEXT_AVAILABILITY_DATE] ?? null);
    $this->setProductNameAndOptions($params[self::KEY_PRODUCT_NAME_AND_OPTIONS] ?? null);
    $this->setDiscontinued($params[self::KEY_DISCONTINUED] ?? null);
  }

  /**
   * @return array
   */
  public function toArray()
  {
    $data = [];
    $data[self::KEY_SUPPLIER_ID] = $this->getSupplierId();
    $data[self::KEY_SUPPLIER_PART_NUMBER] = $this->getSupplierPartNumber();
    $data[self::KEY_QUANTITY_ON_HAND] = $this->getQuantityOnHand();
    $data[self::KEY_QUANTITY_BACKORDER] = $this->getQuantityBackorder();
    $data[self::KEY_QUANTITY_ON_ORDER] = $this->getQuantityOnOrder();
    $data[self::KEY_ITEM_NEXT_AVAILABILITY_DATE] = $this->getItemNextAvailabilityDate();
    $data[self::KEY_PRODUCT_NAME_AND_OPTIONS] = $this->getProductNameAndOptions();
    $data[self::KEY_DISCONTINUED] = $this->isDiscontinued();
    return $data;
  }
}
