<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\Inventory;

class ResponseDTO {
  /**
   * @var int
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
   * @var ErrorDTO[]
   */
  private $errors;

  /**
   * @return int
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param int $id
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
  public function getHandle()
  {
    return $this->handle;
  }

  /**
   * @param string $handle
   *
   * @return void
   */
  public function setHandle($handle)
  {
    $this->handle = $handle;
  }

  /**
   * @return string
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * @param string $status
   *
   * @return void
   */
  public function setStatus($status)
  {
    $this->status = $status;
  }

  /**
   * @return string
   */
  public function getSubmittedAt()
  {
    return $this->submittedAt;
  }

  /**
   * @param string $submittedAt
   *
   * @return void
   */
  public function setSubmittedAt($submittedAt)
  {
    $this->submittedAt = $submittedAt;
  }

  /**
   * @return string
   */
  public function getCompletedAt()
  {
    return $this->completedAt;
  }

  /**
   * @param string $completedAt
   *
   * @return void
   */
  public function setCompletedAt($completedAt)
  {
    $this->completedAt = $completedAt;
  }

  /**
   * @return array
   */
  public function getErrors()
  {
    return $this->errors;
  }

  /**
   * @param mixed $errors
   *
   * @return void
   */
  public function setErrors($errors)
  {
    $this->errors = [];
    foreach ($errors as $key => $error) {
      $this->errors[] = ErrorDTO::createFromArray($error);
    }
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
    $dto->setHandle($params['handle'] ?? null);
    $dto->setStatus($params['status'] ?? null);
    $dto->setSubmittedAt($params['submittedAt'] ?? null);
    $dto->setCompletedAt($params['completedAt'] ?? null);
    $dto->setErrors($params['errors'] ?? []);
    return $dto;
  }

  /**
   * @return array
   */
  public function toArray()
  {
    $data = [];
    $data['id'] = $this->getId();
    $data['handle'] = $this->getHandle();
    $data['status'] = $this->getStatus();
    $data['submittedAt'] = $this->getSubmittedAt();
    $data['completedAt'] = $this->getCompletedAt();
    $data['errors'] = [];
    foreach ($this->getErrors() as $error) {
      $data['errors'][] = $error->toArray();
    }
    return $data;
  }
}
