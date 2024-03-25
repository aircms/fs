<?php

declare(strict_types=1);

namespace App\Controller;

use Air\Core\Exception\ClassWasNotFound;
use App\Service\Fs;
use Exception;

class Index extends Base
{
  /**
   * @return void
   * @throws ClassWasNotFound
   */
  public function init(): void
  {
    parent::init();

    $this->getView()->setAutoRender(false);
  }

  /**
   * @param string $name
   * @param string $path
   * @return array
   */
  public function createFolder(string $name, string $path): array
  {
    try {
      $folder = Fs::createFolder($name, $path);
      return ['path' => $folder->path];

    } catch (\Throwable $e) {
      $this->getResponse()->setStatusCode(400);
      return ['message' => $e->getMessage()];
    }
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
   * @return array
   * @throws ClassWasNotFound
   */
  public function uploadFile(string $path): array
  {
    $errors = Fs::uploadFile($path);

    if (count($errors)) {
      $this->getResponse()->setStatusCode(400);
    }

    return ['errors' => $errors];
  }

  /**
   * @param string $url
   * @param string $path
   * @return array
   * @throws ClassWasNotFound
   */
  public function uploadByUrl(string $url, string $path): array
  {
    return ['path' => Fs::uploadByUrl($url, $path)->path];
  }

  /**
   * @param string|null $path
   * @return void
   * @throws Exception
   */
  public function index(?string $path = Fs::ROOT): void
  {
    $this->getView()->setAutoRender(true);

    $items = Fs::listFolder($path);
    $tree = Fs::tree();

    $this->getView()->assign('items', $items);
    $this->getView()->assign('tree', $tree);
    $this->getView()->assign('workingPath', $path);
  }

  /**
   * @param string|null $path
   * @return void
   * @throws Exception
   */
  public function tree(string $path = null): void
  {
    $this->getView()->setAutoRender(true);
    $this->getView()->assign('tree', Fs::tree($path));
  }

  /**
   * @param string $query
   * @return void
   * @throws ClassWasNotFound
   * @throws Exception
   */
  public function search(string $query): void
  {
    $this->getView()->setAutoRender(true);
    $this->getView()->assign('items', Fs::search($query));
  }

  /**
   * @param array $paths
   * @return void
   * @throws ClassWasNotFound
   */
  public function removeMultiple(array $paths): void
  {
    foreach ($paths as $path) {
      $item = Fs::info($path);

      if ($item->mime === 'directory') {
        Fs::deleteFolder($item->realPath);

      } else {
        Fs::deleteFile($path);
      }
    }
  }
}
