<?php

declare(strict_types=1);

namespace App\Service;

use Air\Core\Exception\ClassWasNotFound;
use Air\Core\Front;
use App\Service\Fs\File;
use App\Service\Fs\Folder;
use ImagickDrawException;
use ImagickException;
use Mimey\MimeTypes;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

class Fs
{
  const string ROOT = '/';

  /**
   * @param string $name
   * @param string|null $path
   * @param bool $recursive
   * @return Folder
   * @throws ClassWasNotFound
   */
  public static function createFolder(string $name, ?string $path = self::ROOT, bool $recursive = false): Folder
  {
    $config = Front::getInstance()->getConfig();
    mkdir(realpath($config['fs']['path']) . $path . '/' . $name, 0755, $recursive);
    return Fs::info($path . '/' . $name);
  }

  /**
   * @param string $path
   * @return void
   */
  public static function deleteFolder(string $path): void
  {
    if (!str_ends_with($path, '/')) {
      $path .= '/';
    }

    $files = glob($path . '*', GLOB_MARK);

    foreach ($files as $file) {

      if (is_dir($file)) {
        self::deleteFolder($file);

      } else {
        unlink($file);
      }
    }

    rmdir($path);
  }

  /**
   * @return array
   */
  private static function mapFiles(): array
  {
    return array_map(function ($name, $type, $tmpName, $error, $size) {
      return [
        'name' => $name,
        'type' => $type,
        'tmpName' => $tmpName,
        'error' => $error,
        'size' => $size
      ];

    }, $_FILES['files']['name'],
      $_FILES['files']['type'],
      $_FILES['files']['tmp_name'],
      $_FILES['files']['error'],
      $_FILES['files']['size']
    );
  }

  /**
   * @param string $path
   * @return array
   * @throws ClassWasNotFound
   */
  public static function uploadFile(string $path): array
  {
    $files = self::mapFiles();

    $errors = [];

    $config = Front::getInstance()->getConfig();

    foreach ($files as $file) {
      try {
        $fileNameParts = explode('.', $file['name']);
        $fileName = md5(microtime()) . '.' . array_pop($fileNameParts);

        copy($file['tmpName'], realpath($config['fs']['path']) . $path . '/' . $fileName);
      } catch (Throwable) {
        $errors[] = 'File "' . $file['name'] . '" was not uploaded.';
      }
    }

    return $errors;
  }

  /**
   * @param string $path
   * @return File[]
   * @throws ClassWasNotFound
   */
  public static function uploadFileApi(string $path): array
  {
    $config = Front::getInstance()->getConfig();
    $files = [];

    foreach (self::mapFiles() as $file) {
      try {
        $fileNameParts = explode('.', $file['name']);
        $fileName = md5(microtime()) . '.' . array_pop($fileNameParts);

        copy($file['tmpName'], realpath($config['fs']['path']) . $path . '/' . $fileName);

        $files[] = Fs::info($path . '/' . $fileName);

      } catch (Throwable) {
      }
    }
    return $files;
  }

  /**
   * @param string $path
   * @param array $data
   * @return File
   * @throws ClassWasNotFound
   */
  public static function uploadData(string $path, array $data): File
  {
    $config = Front::getInstance()->getConfig();

    if ($data['type'] === 'base64') {
      // $data['data'] - data:image/png;base64,...BASE64-CONTENT

      // data:image/png
      $fileName = explode(';', $data['data'])[0];

      // png
      $fileName = explode('/', $fileName)[1];

      $fileName = md5(microtime()) . '.' . trim($fileName);
      $filePath = realpath($config['fs']['path']) . $path . '/' . $fileName;
      file_put_contents($filePath, base64_decode(explode('base64,', $data['data'])[1]));
    } else {
      $fileName = md5(microtime()) . '.' . $data['type'];
      $filePath = realpath($config['fs']['path']) . $path . '/' . $fileName;
      file_put_contents($filePath, $data['data']);
    }

    return Fs::info($path . '/' . $fileName);
  }

  /**
   * @param string $url
   * @param string|null $path
   * @param string|null $name
   * @return File
   * @throws ClassWasNotFound
   */
  public static function uploadByUrl(string $url, ?string $path = self::ROOT, ?string $name = null): File
  {
    if (!$name) {
      $name = md5(microtime());
    }

    $file = file_get_contents($url, false, stream_context_create(['ssl' => ['ciphers' => 'DEFAULT:!DH']]));

    $config = Front::getInstance()->getConfig();

    $filePath = realpath($config['fs']['path']) . $path . '/';

    if (!file_exists($filePath)) {
      self::createFolder($path);
    }

    file_put_contents($filePath . $name, $file);

    $mimes = new MimeTypes();
    $extension = $mimes->getExtension(mime_content_type($filePath . $name));

    rename($filePath . $name, $filePath . $name . '.' . $extension);

    return self::info($path . '/' . $name . '.' . $extension);
  }

  /**
   * @param string $path
   * @return void
   * @throws ClassWasNotFound
   */
  public static function deleteFile(string $path): void
  {
    $config = Front::getInstance()->getConfig()['fs'];
    $file = Fs::info($path);

    if ($file->hasThumbnail()) {
      unlink($config['path'] . $file->getThumbnailPath());
    }
    unlink($file->realPath);
  }

  /**
   * @param string|null $path
   * @return Fs\File[][]|Fs\Folder[][]
   * @throws ClassWasNotFound
   */
  public static function listFolder(?string $path = self::ROOT): array
  {
    $config = Front::getInstance()->getConfig()['fs'];
    $items = glob($config['path'] . $path . '/*');

    return self::prepareItems($items);
  }

  /**
   * @param string|null $path
   * @return File[]|Folder[]
   * @throws ClassWasNotFound
   */
  public static function tree(?string $path = self::ROOT): array
  {
    $config = Front::getInstance()->getConfig()['fs'];
    $items = glob($config['path'] . $path . '/*', GLOB_ONLYDIR);

    return self::prepareItems($items)['folders'];
  }

  /**
   * @param string $query
   * @return Fs\File[][]|Fs\Folder[][]
   * @throws ClassWasNotFound
   */
  public static function search(string $query): array
  {
    $config = Front::getInstance()->getConfig();
    $path = $config['fs']['path'];

    $it = new RecursiveDirectoryIterator($path);
    $items = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator($it) as $file) {
      if (str_contains(strtolower(basename($file->getRealPath())), strtolower($query))) {
        $items[] = $file->getRealPath();
      }
    }

    return self::prepareItems($items);
  }

  /**
   * @param array $items
   * @return File[][]|Folder[][]
   * @throws ClassWasNotFound
   */
  public static function prepareItems(array $items): array
  {
    natcasesort($items);

    $config = Front::getInstance()->getConfig()['fs'];

    $files = [];
    $folders = [];

    foreach ($items as $item) {

      if ($config['path'] . '//' . $config['thumbnails'] === $item) {
        continue;
      }

      $itemPath = substr(realpath($item), strlen(realpath($config['path'])));

      $info = [
        'name' => basename($item),
        'path' => $itemPath,
        'time' => filemtime(realpath($item)),
        'mime' => mime_content_type(realpath($item)),
        'realPath' => $item,
        'dirName' => dirname($item)
      ];

      if (is_file($item)) {
        $info['url'] = $config['url'] . $itemPath;
        $info['size'] = filesize(realpath($item));

        try {
          $dims = getimagesize(realpath($item));

          if ($dims) {
            $info['dims'] = [
              'width' => $dims[0],
              'height' => $dims[1]
            ];
          }
        } catch (Throwable) {
        }

        $files[] = new File($info);

      } else {
        $folders[] = new Folder($info);
      }
    }

    return [
      'files' => $files,
      'folders' => $folders
    ];
  }

  /**
   * @param string $path
   * @return File|Folder
   * @throws ClassWasNotFound
   */
  public static function info(string $path): File|Folder
  {
    $config = Front::getInstance()->getConfig()['fs'];

    $fullPath = realpath($config['path'] . $path);

    $info = [
      'name' => basename($fullPath),
      'path' => $path,
      'time' => filemtime(realpath($fullPath)),
      'mime' => mime_content_type($fullPath),
      'realPath' => $fullPath,
      'dirName' => dirname($fullPath)
    ];

    if (is_file($fullPath)) {
      $info['size'] = filesize($fullPath);
      $info['url'] = $config['url'] . $path;

      try {
        $dims = getimagesize($fullPath);
        if ($dims) {
          $info['dims'] = [
            'width' => $dims[0],
            'height' => $dims[1]
          ];
        }
      } catch (Throwable) {
      }
      return new File($info);
    }
    return new Folder($info);
  }

  /**
   * @param string $folder
   * @param string $fileName
   * @param string $title
   * @param string $backColor
   * @param string $frontColor
   * @return File
   * @throws ClassWasNotFound
   * @throws ImagickDrawException
   * @throws ImagickException
   */
  public static function annotation(
    string $folder,
    string $fileName,
    string $title,
    string $backColor,
    string $frontColor
  ): File
  {
    $im = new \Imagick();
    $im->newImage(500, 500, '#' . $backColor);

    $textDraw = new \ImagickDraw();
    $textDraw->setFontSize(70);
    $textDraw->setFillColor('#' . $frontColor);
    $textDraw->setGravity(\Imagick::ALIGN_CENTER);
    $im->annotateImage($textDraw, 0, 225, 0, $title);
    $im->setImageFormat("png");

    $config = Front::getInstance()->getConfig()['fs'];
    $im->writeImage($config['path'] . '/' . $folder . '/' . $fileName . '.png');
    $im->destroy();

    return self::info('/' . $folder . '/' . $fileName . '.png');
  }
}