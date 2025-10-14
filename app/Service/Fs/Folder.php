<?php

declare(strict_types=1);

namespace App\Service\Fs;

use Throwable;

class Folder extends AbstractItem
{
  public string $mime = 'directory';

  public function __construct(array $item)
  {
    parent::__construct($item);

    try {
      $output = exec("du -s -B1 " . realpath($item['realPath']));
      $this->size = intval(explode("\t", $output)[0]);
    } catch (Throwable) {
    }
  }
}