<?php

declare(strict_types=1);

namespace App\Controller;

use Air\Cookie;
use Air\Core\Controller;
use Air\Core\Exception\ClassWasNotFound;
use Air\Core\Front;
use Exception;

class Base extends Controller
{
  /**
   * @return void
   * @throws ClassWasNotFound
   * @throws Exception
   */
  public function init(): void
  {
    parent::init();

    $key = Front::getInstance()->getConfig()['key'];

    if ($key === $this->getParam('key')) {
      Cookie::set('key', $this->getParam('key'));
    }

    if (!Cookie::get('key') || Cookie::get('key') !== $key) {
      throw new Exception('Not authorized');
    }

    $this->getView()->assign('theme', $this->getParam('theme'));
    $this->getView()->assign('select', $this->getParam('select'));

    if ($this->getRequest()->isAjax()) {
      $this->getView()->setLayoutEnabled(false);
    }
  }
}