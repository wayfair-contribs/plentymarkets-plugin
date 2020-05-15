<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class ProductDTO
{
  /**
   * @var string
   */
  private $partNumber;

  /**
   * @var string
   */
  private $quantity;

  /**
   * @var float
   */
  private $price;

  /**
   * @var int
   */
  private $pieceCount;

  /**
   * @var float
   */
  private $totalCost;

  /**
   * @var string
   */
  private $name;

  /**
   * @var float
   */
  private $weight;

  /**
   * @var float
   */
  private $totalWeight;

  /**
   * @var string
   */
  private $estShipDate;

  /**
   * @var string
   */
  private $fillDate;

  /**
   * @var string
   */
  private $sku;

  /**
   * @var bool
   */
  private $isCancelled;

  /**
   * @var string
   */
  private $twoDayGuaranteeDeliveryDeadline;

  /**
   * @var string
   */
  private $customComment;

  /**
   * @return string
   */
  public function getPartNumber()
  {
    return $this->partNumber;
  }

  /**
   * @param mixed $partNumber
   *
   * @return void
   */
  public function setPartNumber($partNumber)
  {
    $this->partNumber = $partNumber;
  }

  /**
   * @return string
   */
  public function getQuantity()
  {
    return $this->quantity;
  }

  /**
   * @param mixed $quantity
   *
   * @return void
   */
  public function setQuantity($quantity)
  {
    $this->quantity = $quantity;
  }

  /**
   * @return float
   */
  public function getPrice()
  {
    return $this->price;
  }

  /**
   * @param mixed $price
   *
   * @return void
   */
  public function setPrice($price)
  {
    $this->price = $price;
  }

  /**
   * @return int
   */
  public function getPieceCount()
  {
    return $this->pieceCount;
  }

  /**
   * @param mixed $pieceCount
   *
   * @return void
   */
  public function setPieceCount($pieceCount)
  {
    $this->pieceCount = $pieceCount;
  }

  /**
   * @return float
   */
  public function getTotalCost()
  {
    return $this->totalCost;
  }

  /**
   * @param mixed $totalCost
   *
   * @return void
   */
  public function setTotalCost($totalCost)
  {
    $this->totalCost = $totalCost;
  }

  /**
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * @param mixed $name
   *
   * @return void
   */
  public function setName($name)
  {
    $this->name = $name;
  }

  /**
   * @return float
   */
  public function getWeight()
  {
    return $this->weight;
  }

  /**
   * @param mixed $weight
   *
   * @return void
   */
  public function setWeight($weight)
  {
    $this->weight = $weight;
  }

  /**
   * @return float
   */
  public function getTotalWeight()
  {
    return $this->totalWeight;
  }

  /**
   * @param mixed $totalWeight
   *
   * @return void
   */
  public function setTotalWeight($totalWeight)
  {
    $this->totalWeight = $totalWeight;
  }

  /**
   * @return string
   */
  public function getEstShipDate()
  {
    return $this->estShipDate;
  }

  /**
   * @param mixed $estShipDate
   *
   * @return void
   */
  public function setEstShipDate($estShipDate)
  {
    $this->estShipDate = $estShipDate;
  }

  /**
   * @return string
   */
  public function getFillDate()
  {
    return $this->fillDate;
  }

  /**
   * @param mixed $fillDate
   *
   * @return void
   */
  public function setFillDate($fillDate)
  {
    $this->fillDate = $fillDate;
  }

  /**
   * @return string
   */
  public function getSku()
  {
    return $this->sku;
  }

  /**
   * @param mixed $sku
   *
   * @return void
   */
  public function setSku($sku)
  {
    $this->sku = $sku;
  }

  /**
   * @return bool
   */
  public function getIsCancelled()
  {
    return $this->isCancelled;
  }

  /**
   * @param mixed $isCancelled
   *
   * @return void
   */
  public function setIsCancelled($isCancelled)
  {
    $this->isCancelled = $isCancelled;
  }

  /**
   * @return string
   */
  public function getTwoDayGuaranteeDeliveryDeadline()
  {
    return $this->twoDayGuaranteeDeliveryDeadline;
  }

  /**
   * @param mixed $twoDayGuaranteeDeliveryDeadline
   *
   * @return void
   */
  public function setTwoDayGuaranteeDeliveryDeadline($twoDayGuaranteeDeliveryDeadline)
  {
    $this->twoDayGuaranteeDeliveryDeadline = $twoDayGuaranteeDeliveryDeadline;
  }

  /**
   * @return string
   */
  public function getCustomComment()
  {
    return $this->customComment;
  }

  /**
   * @param mixed $customComment
   *
   * @return void
   */
  public function setCustomComment($customComment)
  {
    $this->customComment = $customComment;
  }

  /**
   * Static function to create a new Product DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(ProductDTO::class);
    $dto->setPartNumber($params['partNumber'] ?? null);
    $dto->setQuantity($params['quantity'] ?? null);
    $dto->setPrice($params['price'] ?? null);
    $dto->setPieceCount($params['pieceCount'] ?? null);
    $dto->setTotalCost($params['totalCost'] ?? null);
    $dto->setName($params['name'] ?? null);
    $dto->setWeight($params['weight'] ?? null);
    $dto->setTotalWeight($params['totalWeight'] ?? null);
    $dto->setEstShipDate($params['estShipDate'] ?? null);
    $dto->setFillDate($params['fillDate'] ?? null);
    $dto->setSku($params['sku'] ?? null);
    $dto->setIsCancelled($params['isCancelled'] ?? null);
    $dto->setTwoDayGuaranteeDeliveryDeadline($params['twoDayGuaranteeDeliveryDeadline']);
    return $dto;
  }
}
