<?php

/**
 * @copyright 2020 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\KeyValue;

class KeyValueRepository extends Repository
{

  const LOG_KEY_QUERY_FAILED = "keyValueQueryFailed";

  /**
   * @param mixed $key
   * @param mixed $value
   *
   * @return KeyValue
   * @throws \Exception
   */
  public function put($key, $value)
  {
    if (!isset($key) || empty($key)) {
      throw new ValidationException("Key cannot be empty.");
    }

    if (!isset($value)) {
      // cannot save null values - the underlying DB doesn't allow it
      throw new ValidationException("Value cannot be null.");
    }
    /**
     * @var DataBase
     */
    $database              = pluginApp(DataBase::class);
    /**
     * @var KeyValue
     */
    $keyValueModel         = pluginApp(KeyValue::class);
    $keyValueModel->key    = $key;
    $keyValueModel->value  = $value;
    // this should throw Exceptions on failure
    $database->save($keyValueModel);

    return $keyValueModel;
  }

  /**
   * @param mixed $key
   * @param mixed $value
   *
   * @throws ValidationException
   *
   * @return void
   */
  public function putOrReplace($key, $value)
  {
    if (!isset($key) || empty($key)) {
      throw new ValidationException("Key cannot be empty.");
    }

    $firstModelForKey = null;
    try {
      /**
       * @var DataBase $database
       */
      $database = pluginApp(DataBase::class);
      $modelsForKey = $database->query(KeyValue::class)->where('key', '=', $key)->get();
      if (isset($modelsForKey) && !empty($modelsForKey)) {
        $firstModelForKey = $modelsForKey[0];
      }
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'referenceType' => 'key',
            'referenceValue' => $key,
            'method' => __METHOD__
          ]
        );
    }

    if (!isset($value)) {
      // DB does not accept null values - remove the entry
      if ($firstModelForKey) {
        $database->delete($firstModelForKey);
      }
      return;
    }

    if ($firstModelForKey) {
      $firstModelForKey->value = $value;
      $database->save($firstModelForKey);
    } else {
      $this->put($key, $value);
    }
  }

  /**
   * @param mixed $key
   *
   * @return mixed
   */
  public function get($key)
  {

    $modelsForKey = [];
    try {
      /**
       * @var DataBase $database
       */
      $database      = pluginApp(DataBase::class);
      $modelsForKey = $database->query(KeyValue::class)->where('key', '=', $key)->get();
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'referenceType' => 'key',
            'referenceValue' => $key,
            'method' => __METHOD__
          ]
        );
    }

    if (isset($modelsForKey) && !empty($modelsForKey)) {
      return $modelsForKey[0]->value;
    }

    return null;
  }

  /**
   * @return array
   */
  public function getAll()
  {
    /**
     * @var DataBase $database
     */
    $allModels = [];

    try {
      /**
       * @var DataBase
       */
      $database  = pluginApp(DataBase::class);
      $allModels = $database->query(KeyValue::class)->get();
    } catch (\Exception $e) {
      $this->loggerContract
        ->error(
          TranslationHelper::getLoggerKey(self::LOG_KEY_QUERY_FAILED),
          [
            'additionalInfo' => [
              'exception' => $e,
              'message' => $e->getMessage(),
              'stacktrace' => $e->getTrace()
            ],
            'method' => __METHOD__
          ]
        );
    }

    return $allModels;
  }
}
