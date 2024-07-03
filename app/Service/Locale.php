<?php

declare(strict_types=1);

namespace App\Service;

use Air\Core\Exception\ClassWasNotFound;
use Air\Core\Front;

class Locale
{
  /**
   * @var array|null
   */
  public static ?array $keys = null;

  /**
   * @var string|null
   */
  public static ?string $lang = null;

  /**
   * @param string $lang
   * @return void
   */
  public static function setLang(string $lang): void
  {
    self::$lang = $lang;
  }

  /**
   * @param string $key
   * @return string
   * @throws ClassWasNotFound
   */
  public static function t(string $key): string
  {
    if (!self::$keys) {
      self::$keys = Front::getInstance()->getConfig()['locale'][self::$lang];
    }
    if (!isset(self::$keys[$key])) {
      return $key;
    }
    return self::$keys[$key];
  }

  /**
   * @return array
   * @throws ClassWasNotFound
   */
  public static function phrases(): array
  {
    return Front::getInstance()->getConfig()['locale'][self::$lang];
  }
}
