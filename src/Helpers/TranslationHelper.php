<?php
/**
 * @copyright 2019 Wayfair LLC - All rights reserved
 */

namespace Wayfair\Helpers;

use Plenty\Plugin\Translation\Translator;
use Wayfair\Core\Helpers\AbstractConfigHelper;

/**
 * Class TranslationHelper
 *
 * @package Wayfair\Helpers
 */
class TranslationHelper
{
  /**
   * @var Translator $translator
   */
  private static $translator = null;

  /**
   * @return Translator
   */
  private static function getTranslator()
  {
    if (self::$translator === null) {
      self::$translator = pluginApp(Translator::class);
    }

    return self::$translator;
  }

  /**
   * Translate a message base on input key. Default to template.properties file.
   *
   * @param string $key
   * @param string $file
   *
   * @return mixed
   */
  public static function translate(string $key, string $file = 'template')
  {
    $fullLangKey = AbstractConfigHelper::PLUGIN_NAME . "::{$file}.{$key}";

    return self::getTranslator()->trans($fullLangKey);
  }

  /**
   * Get a Plentymarkets-formatted translation key, as is used for internal logging.
   * @param string $key
   *
   * @return string
   */
  public static function getLoggerKey(string $key): string
  {
    return AbstractConfigHelper::PLUGIN_NAME . '::logger.' . $key;
  }

  /**
   * Get a log message for exception
   *
   * @param string $key
   *
   * @return string
   */
  public static function getLoggerMessage(string $key): string
  {
    $fullLangKey = self::getLoggerKey($key);

    return self::getTranslator()->trans($fullLangKey);
  }
}
