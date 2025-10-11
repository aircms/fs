<?php

declare(strict_types=1);

namespace App\Controller;

use Air\Core\ErrorController;
use Air\Core\Exception\ControllerClassWasNotFound;
use Air\Core\Front;
use App\Service\ImageProcessor;
use Throwable;

class Error extends ErrorController
{
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

  private function propagateImage(): void
  {
    try {
      $params = $this->parseSrc(urldecode($_SERVER['REQUEST_URI']));

      $source = realpath(Front::getInstance()->getConfig()['fs']['path'] . $params['baseUrl']);
      $dest = Front::getInstance()->getConfig()['fs']['path'] . $params['newUrl'];

      $width = $params['width'];
      $height = $params['height'];
      $quality = $params['quality'];

      $image = new ImageProcessor($source);

      if ($width && $height) {
        $image->cropAndResize($width, $height);
      } elseif ($width) {
        $image->resizeToLongSide($width);
      }

      $image->save($dest, quality: $quality);
      $this->redirect(Front::getInstance()->getConfig()['fs']['host'] . $this->getRequest()->getUri());

    } catch (Throwable) {
      $this->redirect(Front::getInstance()->getConfig()['fs']['url'] . $params['baseUrl']);
    }
  }

  private function parseSrc(string $url): array
  {
    $name = basename($url);

    $re = '/^
        (?<base>.+?)
        (?:_r(?<w>\d+)(?:x(?<h>\d+))?)?
        (?:_q(?<q>\d+))?
        \.(?<ext>[^.\/]+)
        $/xu';

    if (!preg_match($re, $name, $m)) {
      $url = str_replace(Front::getInstance()->getConfig()['fs']['folder'] . '/', '', $url);
      return [
        'baseUrl' => $url,
        'width' => null,
        'height' => null,
        'quality' => null,
      ];
    }

    $baseName = $m['base'] . '.' . $m['ext'];
    $baseUrl = substr($url, 0, strrpos($url, $name)) . $baseName;
    $baseUrl = str_replace(Front::getInstance()->getConfig()['fs']['folder'] . '/', '', $baseUrl);
    $url = str_replace(Front::getInstance()->getConfig()['fs']['folder'] . '/', '', $url);

    return [
      'baseUrl' => $baseUrl,
      'newUrl' => $url,
      'width' => isset($m['w']) ? (int)$m['w'] : null,
      'height' => isset($m['h']) ? (int)$m['h'] : null,
      'quality' => isset($m['q']) ? (int)$m['q'] : null,
    ];
  }
}