<?php

declare(strict_types=1);

namespace App\Controller;

use Air\Core\ErrorController;
use Air\Core\Exception\ControllerClassWasNotFound;
use Air\Core\Front;
use App\Service\Url;

class Error extends ErrorController
{
  public function index(): array
  {
    $exception = $this->getException();

    if ($exception instanceof ControllerClassWasNotFound) {
      $this->redirect(Url::propagateImage());
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
}