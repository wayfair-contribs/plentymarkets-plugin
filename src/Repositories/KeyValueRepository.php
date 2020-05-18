<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Repositories;

use Plenty\Exceptions\ValidationException;
use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Wayfair\Core\Helpers\AbstractConfigHelper;
use Wayfair\Helpers\TranslationHelper;
use Wayfair\Models\KeyValue;

class KeyValueRepository extends Repository {

  const LOG_KEY_QUERY_FAILED = "keyValueQueryFailed";

  /**
   * @param mixed $key
   * @param mixed $value
   *
   * @return KeyValue
   * @throws \Exception
   */
  public function put($key, $value) {
    if (empty($key) or empty($value)) {
      throw new ValidationException("Key or Value cannot be empty.");
    }
    /**
     * @var DataBase $database
     */
    $database              = pluginApp(DataBase::class);
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
  public function putOrReplace($key, $value) {
    $firstModelForKey = null;
    try
    {
      /**
       * @var DataBase $database
       */
      $database = pluginApp(DataBase::class);
      $modelsForKey = $database->query(KeyValue::class)->where('key', '=', $key)->get();
      if (isset($modelsForKey) && !empty($modelsForKey))
      {
        $firstModelForKey = $modelsForKey[0];
      }
    }
    catch (\Exception $e) {
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
    
    if ($firstModelForKey) {
      $firstModelForKey->value = $value;
      $database->save($firstModelForKey);
    } else {
      $this->put($key, $value);
    }

    if ($key === AbstractConfigHelper::FULL_INVENTORY_CRON_STATUS) { 
      // TODO: move this to a separate class, or make the KeyValue table to have the updated_at column, or find a better way ...
      $this->putOrReplace(AbstractConfigHelper::FULL_INVENTORY_STATUS_UPDATED_AT, date('Y-m-d H:i:s.u P'));
    }
  }

  /**
   * @param mixed $key
   *
   * @return string|null
   */
  public function get($key) {
    
    $modelsForKey = [];
    try{
      /**
     * @var DataBase $database
     */
      $database      = pluginApp(DataBase::class);
      $modelsForKey = $database->query(KeyValue::class)->where('key', '=', $key)->get();
    }
    catch (\Exception $e) {
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
  public function getAll() {
    /**
     * @var DataBase $database
     */
    $allModels = [];

    try
    {
      $database  = pluginApp(DataBase::class);
      $allModels = $database->query(KeyValue::class)->get();
    }
    catch (\Exception $e) {
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
