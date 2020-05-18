<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\ShipNotice;

/**
 * Class ItemStatusDTO
 *
 * @package Wayfair\Core\Dto\ShipNotice
 */
class ItemStatusDTO {
  /**
   * @var string
   */
  private $key;

  /**
   * @var string
   */
  private $message;

  /**
   * @return string
   */
  public function getKey(): string {
    return $this->key;
  }

  /**
   * @param string $key
   */
  public function setKey(string $key): void {
    $this->key = $key;
  }

  /**
   * @return string
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * @param string $message
   */
  public function setMessage(string $message): void {
    $this->message = $message;
  }

  /**
   * @param array $input
   *
   * @return ItemStatusDTO
   */
  public static function createFromArray(array $input): self {
    /** @var ItemStatusDTO $dto */
    $dto = pluginApp(ItemStatusDTO::class);
    $dto->setKey($input['key'] ?? null);
    $dto->setMessage($input['message'] ?? null);

    return $dto;
  }
}
