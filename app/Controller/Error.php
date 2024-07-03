<?php

declare(strict_types=1);

namespace App\Controller;

use Air\Core\ErrorController;
use Air\Core\Exception\ClassWasNotFound;
use Air\Core\Exception\ControllerClassWasNotFound;
use Air\Core\Front;
use App\Service\ImageResize;
use Exception;
use Throwable;

class Error extends ErrorController
{
  /**
   * @return array
   * @throws ClassWasNotFound
   */
  public function index(): array
  {
    $exception = $this->getException();

    if ($exception instanceof ControllerClassWasNotFound) {
      $this->propagateImage();
    }

    if (Front::getInstance()->getConfig()['air']['exception']) {
      return [
        'error' => true,
        'trace' => $this->getException()->getTrace(),
        'message' => $this->getException()->getMessage()
      ];
    }

    return [
      'error' => true,
      'message' => $this->getException()->getMessage()
    ];
  }

  /**
   * @return void
   */
  private function propagateImage(): void
  {
    try {
      $dest = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'])));
      $dest[0] = Front::getInstance()->getConfig()['fs']['path'];
      $dest = implode('/', $dest);

      $realFileName = explode('/', $dest);
      $realFileName = $realFileName[count($realFileName) - 1];
      $realFileName = explode('_r_', $realFileName);
      $ext = explode('.', $realFileName[1]);

      if ($ext[1] !== 'png' && $ext[1] !== 'jpg' && $ext[1] !== 'jpeg') {
        return;
      }

      $sourceFolder = explode('/', $dest);
      unset($sourceFolder[count($sourceFolder) - 1]);
      $source = implode('/', $sourceFolder) . '/' . $realFileName[0] . '.' . $ext[1];

      if (!file_exists($source)) {
        return;
      }

      $width = null;
      $height = null;

      try {
        $width = (int)explode('x', $ext[0])[0];
      } catch (Exception) {
      }

      try {
        $height = (int)explode('x', $ext[0])[1];
      } catch (Exception) {
      }

      $image = new ImageResize($source);

      if ($width && $height) {
        $image->crop($width, $height, true);
      } else {
        $image->resizeToLongSide($width);
      }

      $image->save($dest);
      $this->redirect($_SERVER['REQUEST_URI']);

    } catch (Throwable) {
    }
  }
}