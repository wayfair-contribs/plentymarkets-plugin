<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\Inventory;

class ResponseDTO
{

  const KEY_ID = 'id';
  const KEY_HANDLE = 'handle';
  const KEY_STATUS = 'status';
  const KEY_SUBMITTED_AT = 'submittedAt';
  const KEY_COMPLETED_AT = 'completedAt';
  const KEY_ERRORS = 'errors';

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
   * Adopt the array's data into this DTO
   *
   * @param array $params Params
   *
   * @return void
   */
  public function adoptArray(array $params): void
  {
    $this->setId($params[self::KEY_ID] ?? null);
    $this>setHandle($params[self::KEY_HANDLE] ?? null);
    $this->setStatus($params[self::KEY_STATUS] ?? null);
    $this->setSubmittedAt($params[self::KEY_SUBMITTED_AT] ?? null);
    $this->setCompletedAt($params[self::KEY_COMPLETED_AT] ?? null);
    $this->setErrors($params[self::KEY_ERRORS] ?? []);
  }

  /**
   * @return array
   */
  public function toArray()
  {
    $data = [];
    $data[self::KEY_ID] = $this->getId();
    $data[self::KEY_HANDLE] = $this->getHandle();
    $data[self::KEY_STATUS] = $this->getStatus();
    $data[self::KEY_SUBMITTED_AT] = $this->getSubmittedAt();
    $data[self::KEY_COMPLETED_AT] = $this->getCompletedAt();
    $data[self::KEY_ERRORS] = [];
    foreach ($this->getErrors() as $error) {
      $data[self::KEY_ERRORS][] = $error->toArray();
    }
    return $data;
  }
}
