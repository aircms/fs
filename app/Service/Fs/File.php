<?php

declare(strict_types=1);

namespace App\Service\Fs;

use Air\Core\Front;
use App\Service\Fs;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Imagick;
use Throwable;

class File extends AbstractItem
{
  public string $url = '';

  public array $dims = [
    'width' => 0,
    'height' => 0
  ];

  public string $ext;

  public function __construct(array $item)
  {
    parent::__construct($item);

    $fileParts = explode('.', $this->name);
    $this->ext = $fileParts[count($fileParts) - 1];
  }

  public function getThumbnailPath(): string
  {
    $thumbFilename = md5($this->path);
    $config = Front::getInstance()->getConfig()['fs'];
    $ext = $this->ext;

    if (!str_contains($this->mime, 'image')) {
      $ext = 'png';
    }

    return '/' . $config['thumbnails'] . '/' . $thumbFilename . '.' . $ext;
  }

  public function hasThumbnail(): bool
  {
    try {
      $config = Front::getInstance()->getConfig()['fs'];
      return file_exists($config['path'] . $this->getThumbnailPath());
    } catch (Throwable) {
    }
    return false;
  }

  public function getThumbnail(): string
  {
    $config = Front::getInstance()->getConfig()['fs'];
    $thumbnailPath = $this->getThumbnailPath();

    if ($this->hasThumbnail()) {
      return '/' . $config['folder'] . $thumbnailPath;
    }

    try {
      Fs::createFolder('/' . $config['thumbnails'], null, true);
    } catch (Throwable) {
    }

    $im = new Imagick();

    try {
      if (str_contains($this->mime, 'video')) {
        $ffMpeg = FFMpeg::create();
        $video = $ffMpeg->open($this->realPath);
        $frame = $video->frame(TimeCode::fromSeconds(0));
        $im->readImageBlob($frame->save(null, true, true));

      } elseif (str_contains($this->mime, 'pdf')) {
        $im->readImage($this->realPath . '[0]');
        $im->setImageFormat('png');

      } elseif (str_contains($this->mime, 'gif')) {
        /**
         * TODO: Implement scaling animated images
         */
        $im->readImage($this->realPath . '[0]');

      } elseif (str_contains($this->mime, 'image')) {
        $im->readImage($this->realPath);
      }

      $width = 300;
      $height = 180;

      if ($im->getImageWidth() > $width) {
        $im->cropThumbnailImage($width, $height);
      }

      $im->writeImage($config['path'] . $thumbnailPath);
      $im->clear();

      return '/' . $config['folder'] . $thumbnailPath;

    } catch (Throwable) {
      copy($this->realPath, $config['path'] . $thumbnailPath);
      return '/' . $config['folder'] . $thumbnailPath;
    }
  }

  public function getSimplifyMime(): string
  {
    $types = ['image', 'video', 'pdf', 'text'];

    foreach ($types as $type) {
      if (str_contains($this->mime, $type)) {
        return $type;
      }
    }

    return 'file';
  }

  public function toArray(): array
  {
    return [
      'size' => $this->size,
      'mime' => $this->mime,
      'time' => $this->time,
      'dims' => $this->dims,
      'ext' => $this->ext,
      'thumbnail' => $this->getThumbnail(),
      'src' => '/' . Front::getInstance()->getConfig()['fs']['folder'] . $this->path,
    ];
  }

  public function toJSON(): string
  {
    return htmlspecialchars(json_encode($this->toArray()), ENT_QUOTES, 'UTF-8');
  }
}