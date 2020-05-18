<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Http;

class WayfairResponse {

  /**
   * @var mixed
   */
  private $error;

  /**
   * @var mixed
   */
  private $body;

  /**
   * @var mixed
   */
  private $statusCode;

  /**
   * @var mixed
   */
  private $reasonPhrase;

  /**
   * @var mixed
   */
  private $headers;

  /**
   * WayfairResponse constructor.
   *
   * @param array $data
   */
  public function __construct($data = []) {
    $this->error        = $data['error'];
    $this->statusCode   = $data['statusCode'];
    $this->reasonPhrase = $data['reasonPhrase'];
    $this->body         = $data['body'];
    $this->headers      = $data['headers'];
  }

  /**
   * @return bool
   */
  public function hasErrors() {
    $bodyAsArray = $this->getBodyAsArray();
    return !empty($this->error) or !empty($this->getErrorFromArray());
  }

  /**
   * @return mixed
   */
  public function getHeaders() {
    return $this->headers;
  }

  /**
   * @return mixed
   */
  public function getError() {
    if (!empty($this->error))
    {
      return $this->error;
    }

    return $this->getErrorFromArray();
  }

  /**
   * @return mixed
   */
  public function getBody() {
    return $this->body;
  }

  /**
   * @return array
   */
  public function getBodyAsArray() {
    return json_decode($this->body, true);
  }
  /**
   * @return mixed
   */
  public function getStatusCode() {
    return $this->statusCode;
  }

  /**
   * @return mixed
   */
  public function getReasonPhrase() {
    return $this->reasonPhrase;
  }

  /**
   * @return mixed
   */
  private function getErrorFromArray() {
    // some modules were looking for an 'error' element
    // whilst others were looking for an 'errors' element.
    $bodyAsArray = $this->getBodyAsArray();
    if (array_key_exists('error', $bodyAsArray))
    {
      return $bodyAsArray['error'];
    }

    return $bodyAsArray['errors'];
  }
}