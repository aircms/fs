<?php

declare(strict_types=1);

namespace App\Service\Fs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Folder extends AbstractItem
{
  public string $mime = 'directory';

  public array $itemsCount = [
    'folders' => 0,
    'files' => 0,
  ];

  public function __construct(array $item)
  {
    parent::__construct($item);

    $this->size = 0;
    $path = realpath($this->realPath);
    $directoryIterator = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);

    /** @var SplFileInfo $object */
    foreach (new RecursiveIteratorIterator($directoryIterator) as $object) {
      $this->size += $object->getSize();
    }

    $filesCount = 0;
    $foldersCount = 0;

    $path = realpath($this->realPath);
    $directoryIterator = new RecursiveDirectoryIterator($path);

    /** @var SplFileInfo $object */
    foreach (new RecursiveIteratorIterator($directoryIterator) as $object) {
      if ($object->isDir() && $object->getFilename() !== '.' && $object->getFilename() !== '..' && $object->getRealPath() !== $path) {
        $foldersCount++;
      } elseif ($object->isFile()) {
        $filesCount++;
      }
    }

    $this->itemsCount = [
      'files' => $filesCount,
      'folders' => $foldersCount
    ];
  }

  public function getSize(?bool $decimal = true): string
  {
    return $this->formatBytes($this->size, $decimal);
  }

  public function getItemsCount(): array
  {
    return $this->itemsCount;
  }
}