<?php

declare(strict_types=1);

namespace App\Service\Fs;

abstract class AbstractItem
{
  public string $name;
  public string $path;
  public string $realPath = '';
  public string $dirName = '';
  public int $time;
  public string $mime;
  public int $size = 0;

  /**
   * @param array $item
   */
  public function __construct(array $item)
  {
    foreach ($item as $key => $value) {
      $this->{$key} = $value;
    }
  }

  /**
   * @param bool|null $decimal
   * @return string
   */
  public function getSize(?bool $decimal = true): string
  {
    if (!$this->size) {
      return '0b';
    }

    return $this->formatBytes($this->size);
  }

  /**
   * @param int $bytes
   * @param bool|null $decimal
   * @return string
   */
  public function formatBytes(int $bytes, ?bool $decimal = true): string
  {
    $kilobyte = 1024;
    $megabyte = $kilobyte * 1024;
    $gigabyte = $megabyte * 1024;

    if ($bytes < $kilobyte) {
      return $bytes . ' b';

    } elseif ($bytes < $megabyte) {
      return round($bytes / $kilobyte, $decimal ? 2 : 0) . ' kb';

    } elseif ($bytes < $gigabyte) {
      return round($bytes / $megabyte, $decimal ? 2 : 0) . ' mb';
    }

    return round($bytes / $gigabyte, $decimal ? 2 : 0) . ' gb';
  }
}