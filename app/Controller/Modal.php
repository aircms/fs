<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Fs;

class Modal extends Base
{
  public function view(string $path): void
  {
    $this->getView()->assign('file', Fs::info($path));
  }

  public function remove(string $path): void
  {
    $this->getView()->assign('file', Fs::info($path));
  }

  public function removeFolder(string $path): void
  {
    $this->getView()->assign('folder', Fs::info($path));
  }

  public function createFolder(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }

  public function uploadFromComputer(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }

  public function uploadByUrl(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }
}