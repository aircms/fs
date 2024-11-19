<?php

declare(strict_types=1);

namespace App\Controller;

use Air\Core\Exception\ClassWasNotFound;
use App\Service\Fs;
use App\Service\Fs\File;
use ImagickDrawException;
use ImagickException;
use Throwable;

class Api extends Base
{
  /**
   * @return void
   * @throws ClassWasNotFound
   */
  public function init(): void
  {
    parent::init();

    $this->getView()->setLayoutEnabled(false);
    $this->getView()->setAutoRender(false);
  }

  /**
   * @param string $path
   * @return void
   * @throws ClassWasNotFound
   */
  public function deleteFolder(string $path): void
  {
    $folder = Fs::info($path);
    Fs::deleteFolder($folder->realPath);
  }

  /**
   * @param array $paths
   * @return array
   * @throws ClassWasNotFound
   */
  public function info(array $paths): array
  {
    $files = [];
    foreach ($paths as $path) {
      $files[] = Fs::info($path)->toArray();
    }
    return $files;
  }

  /**
   * @param string $url
   * @param string|null $path
   * @param string|null $name
   * @return array
   * @throws ClassWasNotFound
   */
  public function uploadByUrl(string $url, ?string $path = Fs::ROOT, ?string $name = null): array
  {
    return Fs::uploadByUrl($url, $path, $name)->toArray();
  }

  /**
   * @param string $path
   * @return void
   * @throws ClassWasNotFound
   */
  public function deleteFile(string $path): void
  {
    Fs::deleteFile($path);
  }

  /**
   * @param string $path
   * @return File[]
   * @throws ClassWasNotFound
   */
  public function uploadFile(string $path): array
  {
    Fs::createFolder($path, Fs::ROOT, true);
    $files = [];
    foreach (Fs::uploadFileApi($path) as $file) {
      $files[] = $file->toArray();
    }
    return $files;
  }

  /**
   * @param string $name
   * @param string $path
   * @param bool $recursive
   * @return void
   * @throws ClassWasNotFound
   */
  public function createFolder(string $name, string $path, bool $recursive = false): void
  {
    Fs::createFolder($name, $path, $recursive);
  }

  /**
   * @param string $path
   * @param array $datum
   * @return array
   * @throws ClassWasNotFound
   */
  public function uploadDatum(string $path, array $datum): array
  {
    Fs::createFolder($path, Fs::ROOT, true);

    $files = [];
    foreach ($datum as $index => $data) {
      try {
        $files[$index] = Fs::uploadData($path, $data)->toArray();
      } catch (Throwable) {

      }
    }
    return $files;
  }

  /**
   * @param string $folder
   * @param string $fileName
   * @param string $title
   * @param string $backColor
   * @param string $frontColor
   * @return array
   * @throws ClassWasNotFound
   * @throws ImagickDrawException
   * @throws ImagickException
   */
  public function annotation(
    string $folder,
    string $fileName,
    string $title,
    string $backColor,
    string $frontColor
  ): array
  {
    return Fs::annotation($folder, $fileName, $title, $backColor, $frontColor)->toArray();
  }
}