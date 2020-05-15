<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\ShippingLabelInfo;

class ResponseDTO
{
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
   * Static function to create a new Response DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self
  {
    $dto = pluginApp(ResponseDTO::class);
    $dto->setId($params['id'] ?? null);
    $dto->setEventDate($params['eventDate'] ?? null);
    $dto->setPickupDate($params['pickupDate'] ?? null);
    $dto->setPoNumber($params['poNumber'] ?? null);
    return $dto;
  }
}
