<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\ShipNotice;

/**
 * Class ResponseDTO
 *
 * @package Wayfair\Core\Dto\ShipNotice
 */
class ResponseDTO {
  /**
   * @var string
   */
  private $id;

  /**
   * @var string
   */
  private $handle;

  /**
   * @var string
   */
  private $status;

  /**
   * @var string
   */
  private $submittedAt;

  /**
   * @var string
   */
  private $completedAt;

  /**
   * @var ItemStatusDTO
   */
  private $errors;

  /**
   * @var ItemStatusDTO
   */
  private $completed;

  /**
   * @var ItemStatusDTO
   */
  private $processing;

  /**
   * @return string
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @param string $id
   */
  public function setId($id) {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getHandle() {
    return $this->handle;
  }

  /**
   * @param string $handle
   */
  public function setHandle($handle) {
    $this->handle = $handle;
  }

  /**
   * @return string
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * @param string $status
   */
  public function setStatus($status) {
    $this->status = $status;
  }

  /**
   * @return string
   */
  public function getSubmittedAt() {
    return $this->submittedAt;
  }

  /**
   * @param string $submittedAt
   */
  public function setSubmittedAt($submittedAt) {
    $this->submittedAt = $submittedAt;
  }

  /**
   * @return string
   */
  public function getCompletedAt() {
    return $this->completedAt;
  }

  /**
   * @param string $completedAt
   */
  public function setCompletedAt($completedAt) {
    $this->completedAt = $completedAt;
  }

  /**
   * @return ItemStatusDTO
   */
  public function getErrors() {
    return $this->errors;
  }

  /**
   * @param ItemStatusDTO $errors
   */
  public function setErrors($errors) {
    $this->errors = $errors;
  }

  /**
   * @return ItemStatusDTO
   */
  public function getCompleted() {
    return $this->completed;
  }

  /**
   * @param ItemStatusDTO $completed
   */
  public function setCompleted($completed) {
    $this->completed = $completed;
  }

  /**
   * @return ItemStatusDTO
   */
  public function getProcessing() {
    return $this->processing;
  }

  /**
   * @param ItemStatusDTO $processing
   */
  public function setProcessing($processing) {
    $this->processing = $processing;
  }

  /**
   * Create response object from array.
   *
   * @param array $input
   *
   * @return ResponseDTO
   */
  public static function createFromArray(array $input): self {
    /** @var ResponseDTO $dto */
    $dto = pluginApp(ResponseDTO::class);

    $dto->setId($input['id'] ?? null);
    $dto->setHandle($input['handle'] ?? null);
    $dto->setStatus($input['status'] ?? null);
    $dto->setSubmittedAt($input['submittedAt'] ?? null);
    $dto->setCompletedAt($input['completedAt'] ?? null);
    $dto->setErrors(!empty($input['errors']) ? ItemStatusDTO::createFromArray($input['errors']) : null);
    $dto->setCompleted(!empty($input['completed']) ? ItemStatusDTO::createFromArray($input['completed']) : null);
    $dto->setProcessing(!empty($input['processing']) ? ItemStatusDTO::createFromArray($input['processing']) : null);

    return $dto;
  }
}
