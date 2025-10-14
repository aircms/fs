<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Fs;
use Throwable;

class Index extends Base
{
  public function init(): void
  {
    parent::init();

    $this->getView()->setAutoRender(false);
  }

  public function createFolder(string $name, string $path): array
  {
    try {
      $folder = Fs::createFolder($name, $path);
      return ['path' => $folder->path];

    } catch (Throwable $e) {
      $this->getResponse()->setStatusCode(400);
      return ['message' => $e->getMessage()];
    }
  }

  public function uploadFile(string $path): array
  {
    $errors = Fs::uploadFile($path);

    if (count($errors)) {
      $this->getResponse()->setStatusCode(400);
    }

    return ['errors' => $errors];
  }

  public function uploadByUrl(string $url, string $path): array
  {
    return ['path' => Fs::uploadByUrl($url, $path)->path];
  }

  public function index(?string $path = Fs::ROOT): void
  {
    $this->getView()->setAutoRender(true);

    $this->getView()->assign('items', Fs::listFolder($path));
    $this->getView()->assign('workingPath', $path);
  }

  public function search(string $query): void
  {
    $this->getView()->setAutoRender(true);
    $this->getView()->assign('items', Fs::search($query));
  }

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

  public function deleteFolder(string $path): void
  {
    $folder = Fs::info($path);
    Fs::deleteFolder($folder->realPath);
  }

  public function deleteFile(string $path): void
  {
    Fs::deleteFile($path);
  }
}
