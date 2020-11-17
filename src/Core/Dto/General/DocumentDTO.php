<?php
/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Core\Dto\General;

class DocumentDTO {

  /**
   * DocumentDTO constructor.
   *
   * @param array $data
   */
  public function __construct($data = []) {
    $this->setFileContent($data['fileContent'] ?? '');
  }

  /**
   * Return base 64 encoded of the file content.
   *
   * @return string
   */
  public function getBase64EncodedContent() {
    if (!empty($this->fileContent)) {
      return base64_encode($this->fileContent);
    }

    return '';
  }

  /**
   * @var string
   */
  private $fileContent;

  /**
   * @return string
   */
  public function getFileContent() {
    return $this->fileContent;
  }

  /**
   * @param mixed $fileContent
   *
   * @return void
   */
  public function setFileContent($fileContent) {
    $this->fileContent = $fileContent;
  }

  /**
   * Static function to create a new Response DTO from array
   *
   * @param array $params Params
   *
   * @return self
   */
  public static function createFromArray(array $params): self {
    /** @var DocumentDTO */
    $dto = pluginApp(DocumentDTO::class);
    $dto->setFileContent($params['fileContent'] ?? null);
    return $dto;
  }
}
