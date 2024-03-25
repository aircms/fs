<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Fs;
use Exception;

class Modal extends Base
{
  /**
   * @param string $path
   * @return void
   * @throws Exception
   */
  public function view(string $path): void
  {
    $this->getView()->assign('file', Fs::info($path));
  }

  /**
   * @param string $path
   * @return void
   * @throws Exception
   */
  public function remove(string $path): void
  {
    $this->getView()->assign('file', Fs::info($path));
  }

  /**
   * @param string $path
   * @return void
   * @throws Exception
   */
  public function removeFolder(string $path): void
  {
    $this->getView()->assign('folder', Fs::info($path));
  }

  /**
   * @param string $path
   * @return void
   * @throws Exception
   */
  public function createFolder(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }

  /**
   * @param string $path
   * @return void
   * @throws Exception
   */
  public function uploadFromComputer(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }

  /**
   * @param string $path
   * @return void
   * @throws Exception
   */
  public function uploadByUrl(string $path): void
  {
    $this->getView()->assign('workingPath', $path);
  }
}