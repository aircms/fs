<?php

declare(strict_types=1);

namespace App\Service;

use Exception;

class ImageResize
{
  const CROP_CENTRE = 2;
  const CROP_CENTER = 2;
  const CROP_BOTTOM = 3;
  const CROP_RIGHT = 5;
  const CROP_TOP_CENTER = 6;
  const IMG_FLIP_HORIZONTAL = 0;
  const IMG_FLIP_VERTICAL = 1;
  const IMG_FLIP_BOTH = 2;

  public int $qualityJpg = 85;
  public int $qualityWebp = 85;
  public int $qualityPng = 6;
  public bool $qualityTrueColor = true;
  public bool $gammaCorrect = true;

  public bool $interlace = true;

  public int $sourceType;

  protected $sourceImage;

  protected int $originalW;
  protected int $originalH;

  protected int $destX;
  protected int $destY;

  protected int $sourceX;
  protected int $sourceY;

  protected int $destW;
  protected int $destH;

  protected int $sourceW;
  protected int $sourceH;

  protected mixed $sourceInfo;

  protected array $filters = [];

  /**
   * @param string $imageData
   * @return $this
   * @throws ImageResizeException
   */
  public static function createFromString(string $imageData): static
  {
    if (empty($imageData)) {
      throw new ImageResizeException('imageData must not be empty');
    }
    return new self('data://application/octet-stream;base64,' . base64_encode($imageData));
  }

  /**
   * @param callable $filter
   * @return $this
   */
  public function addFilter(callable $filter): static
  {
    $this->filters[] = $filter;
    return $this;
  }

  /**
   * @param mixed $image
   * @param int $filterType
   * @return void
   */
  protected function applyFilter(mixed $image, int $filterType = IMG_FILTER_NEGATE): void
  {
    foreach ($this->filters as $function) {
      $function($image, $filterType);
    }
  }

  /**
   * Loads image source and its properties to the instanciated object
   *
   * @param string $filename
   * @return ImageResize
   * @throws ImageResizeException
   */
  public function __construct(string $filename)
  {
    if (!defined('IMAGETYPE_WEBP')) {
      define('IMAGETYPE_WEBP', 18);
    }

    if (empty($filename) || (!str_starts_with($filename, 'data:') && !is_file($filename))) {
      throw new ImageResizeException('File does not exist');
    }

    $fInfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!str_contains(finfo_file($fInfo, $filename), 'image')) {
      throw new ImageResizeException('Unsupported file type');
    }

    if (!$imageInfo = getimagesize($filename, $this->sourceInfo)) {
      $imageInfo = getimagesize($filename);
    }

    if (!$imageInfo) {
      throw new ImageResizeException('Could not read file');
    }

    list(
      $this->originalW,
      $this->originalH,
      $this->sourceType
      ) = $imageInfo;

    switch ($this->sourceType) {
      case IMAGETYPE_GIF:
        $this->sourceImage = imagecreatefromgif($filename);
        break;

      case IMAGETYPE_JPEG:
        $this->sourceImage = $this->imageCreateJpegfromExif($filename);

        // set new width and height for image, maybe it has changed
        $this->originalW = imagesx($this->sourceImage);
        $this->originalH = imagesy($this->sourceImage);

        break;

      case IMAGETYPE_PNG:
        $this->sourceImage = imagecreatefrompng($filename);
        break;

      case IMAGETYPE_WEBP:
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
          throw new ImageResizeException('For WebP support PHP >= 5.5.0 is required');
        }
        $this->sourceImage = imagecreatefromwebp($filename);
        break;

      default:
        throw new ImageResizeException('Unsupported image type');
    }

    if (!$this->sourceImage) {
      throw new ImageResizeException('Could not load image');
    }

    return $this->resize($this->getSourceWidth(), $this->getSourceHeight());
  }

  // http://stackoverflow.com/a/28819866
  public function imageCreateJpegfromExif($filename)
  {
    $img = imagecreatefromjpeg($filename);

    if (!function_exists('exif_read_data') || !isset($this->sourceInfo['APP1']) || strpos($this->sourceInfo['APP1'], 'Exif') !== 0) {
      return $img;
    }

    try {
      $exif = @exif_read_data($filename);
    } catch (Exception $e) {
      $exif = null;
    }

    if (!$exif || !isset($exif['Orientation'])) {
      return $img;
    }

    $orientation = $exif['Orientation'];

    if ($orientation === 6 || $orientation === 5) {
      $img = imagerotate($img, 270, 0);

    } elseif ($orientation === 3 || $orientation === 4) {
      $img = imagerotate($img, 180, 0);

    } elseif ($orientation === 8 || $orientation === 7) {
      $img = imagerotate($img, 90, 0);
    }

    if ($orientation === 5 || $orientation === 4 || $orientation === 7) {
      if (function_exists('imageflip')) {
        imageflip($img, IMG_FLIP_HORIZONTAL);
      } else {
        $this->imageFlip($img, IMG_FLIP_HORIZONTAL);
      }
    }

    return $img;
  }

  /**
   * @param string|null $filename
   * @param int|null $imageType
   * @param int|null $quality
   * @param int|null $permissions
   * @param bool $exactSize
   * @return $this
   * @throws ImageResizeException
   */
  public function save(
    string $filename = null,
    int    $imageType = null,
    int    $quality = null,
    int    $permissions = null,
    bool   $exactSize = false
  ): static
  {
    $imageType = $imageType ?: $this->sourceType;
    $quality = is_numeric($quality) ? (int)abs($quality) : null;

    switch ($imageType) {
      case IMAGETYPE_GIF:
        if (!empty($exactSize) && is_array($exactSize)) {
          $destImage = imagecreatetruecolor($exactSize[0], $exactSize[1]);
        } else {
          $destImage = imagecreatetruecolor($this->getDestWidth(), $this->getDestHeight());
        }

        $background = imagecolorallocatealpha($destImage, 255, 255, 255, 1);
        imagecolortransparent($destImage, $background);
        imagefill($destImage, 0, 0, $background);
        imagesavealpha($destImage, true);
        break;

      case IMAGETYPE_JPEG:
        if (!empty($exactSize) && is_array($exactSize)) {
          $destImage = imagecreatetruecolor($exactSize[0], $exactSize[1]);
          $background = imagecolorallocate($destImage, 255, 255, 255);
          imagefilledrectangle($destImage, 0, 0, $exactSize[0], $exactSize[1], $background);

        } else {
          $destImage = imagecreatetruecolor($this->getDestWidth(), $this->getDestHeight());
          $background = imagecolorallocate($destImage, 255, 255, 255);
          imagefilledrectangle($destImage, 0, 0, $this->getDestWidth(), $this->getDestHeight(), $background);
        }
        break;

      case IMAGETYPE_WEBP:
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
          throw new ImageResizeException('For WebP support PHP >= 5.5.0 is required');
        }
        if (!empty($exactSize) && is_array($exactSize)) {
          $destImage = imagecreatetruecolor($exactSize[0], $exactSize[1]);
          $background = imagecolorallocate($destImage, 255, 255, 255);
          imagefilledrectangle($destImage, 0, 0, $exactSize[0], $exactSize[1], $background);

        } else {
          $destImage = imagecreatetruecolor($this->getDestWidth(), $this->getDestHeight());
          $background = imagecolorallocate($destImage, 255, 255, 255);
          imagefilledrectangle($destImage, 0, 0, $this->getDestWidth(), $this->getDestHeight(), $background);
        }
        break;

      case IMAGETYPE_PNG:
        if (!$this->qualityTrueColor && !imageistruecolor($this->sourceImage)) {
          if (!empty($exactSize) && is_array($exactSize)) {
            $destImage = imagecreate($exactSize[0], $exactSize[1]);
          } else {
            $destImage = imagecreate($this->getDestWidth(), $this->getDestHeight());
          }
        } else {
          if (!empty($exactSize) && is_array($exactSize)) {
            $destImage = imagecreatetruecolor($exactSize[0], $exactSize[1]);
          } else {
            $destImage = imagecreatetruecolor($this->getDestWidth(), $this->getDestHeight());
          }
        }

        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);

        $background = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagecolortransparent($destImage, $background);
        imagefill($destImage, 0, 0, $background);
        break;
    }

    imageinterlace($destImage, $this->interlace);

    if ($this->gammaCorrect) {
      imagegammacorrect($this->sourceImage, 2.2, 1.0);
    }

    if (!empty($exactSize) && is_array($exactSize)) {
      if ($this->getSourceHeight() < $this->getSourceWidth()) {
        $this->destX = 0;
        $this->destY = ($exactSize[1] - $this->getDestHeight()) / 2;
      }
      if ($this->getSourceHeight() > $this->getSourceWidth()) {
        $this->destX = ($exactSize[0] - $this->getDestWidth()) / 2;
        $this->destY = 0;
      }
    }

    imagecopyresampled(
      $destImage,
      $this->sourceImage,
      intval($this->destX ?? 0),
      intval($this->destY ?? 0),
      $this->sourceX,
      $this->sourceY,
      $this->getDestWidth(),
      $this->getDestHeight(),
      $this->sourceW,
      $this->sourceH
    );

    if ($this->gammaCorrect) {
      imagegammacorrect($destImage, 1.0, 2.2);
    }

    $this->applyFilter($destImage);

    switch ($imageType) {
      case IMAGETYPE_GIF:
        imagegif($destImage, $filename);
        break;

      case IMAGETYPE_JPEG:
        if ($quality === null || $quality > 100) {
          $quality = $this->qualityJpg;
        }

        imagejpeg($destImage, $filename, $quality);
        break;

      case IMAGETYPE_WEBP:
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
          throw new ImageResizeException('For WebP support PHP >= 5.5.0 is required');
        }
        if ($quality === null) {
          $quality = $this->qualityWebp;
        }

        imagewebp($destImage, $filename, $quality);
        break;

      case IMAGETYPE_PNG:
        if ($quality === null || $quality > 9) {
          $quality = $this->qualityPng;
        }

        imagepng($destImage, $filename, $quality);
        break;
    }

    if ($permissions) {
      chmod($filename, $permissions);
    }

    imagedestroy($destImage);

    return $this;
  }

  /**
   * @param int|null $imageType
   * @param int|null $quality
   * @return false|string
   * @throws ImageResizeException
   */
  public function getImageAsString(int $imageType = null, int $quality = null): false|string
  {
    $string_temp = tempnam(sys_get_temp_dir(), '');

    $this->save($string_temp, $imageType, $quality);

    $string = file_get_contents($string_temp);

    unlink($string_temp);

    return $string;
  }

  /**
   * @return false|string
   * @throws ImageResizeException
   */
  public function __toString()
  {
    return $this->getImageAsString();
  }

  /**
   * @param int|null $imageType
   * @param int|null $quality
   * @return void
   * @throws ImageResizeException
   */
  public function output(int $imageType = null, int $quality = null): void
  {
    $imageType = $imageType ?: $this->sourceType;

    header('Content-Type: ' . image_type_to_mime_type($imageType));

    $this->save(null, $imageType, $quality);
  }

  /**
   * @param int $maxShort
   * @param bool $allowEnlarge
   * @return $this
   */
  public function resizeToShortSide(int $maxShort, bool $allowEnlarge = false): static
  {
    if ($this->getSourceHeight() < $this->getSourceWidth()) {
      $ratio = $maxShort / $this->getSourceHeight();
      $long = $this->getSourceWidth() * $ratio;

      $this->resize($long, $maxShort, $allowEnlarge);
    } else {
      $ratio = $maxShort / $this->getSourceWidth();
      $long = $this->getSourceHeight() * $ratio;

      $this->resize($maxShort, $long, $allowEnlarge);
    }

    return $this;
  }

  /**
   * @param int $maxLong
   * @param bool $allowEnlarge
   * @return $this
   */
  public function resizeToLongSide(int $maxLong, bool $allowEnlarge = false): static
  {
    if ($this->getSourceHeight() > $this->getSourceWidth()) {
      $ratio = $maxLong / $this->getSourceHeight();
      $short = intval($this->getSourceWidth() * $ratio);

      $this->resize($short, $maxLong, $allowEnlarge);
    } else {
      $ratio = $maxLong / $this->getSourceWidth();
      $short = intval($this->getSourceHeight() * $ratio);

      $this->resize($maxLong, $short, $allowEnlarge);
    }

    return $this;
  }

  /**
   * @param int $height
   * @param bool $allowEnlarge
   * @return $this
   */
  public function resizeToHeight(int $height, bool $allowEnlarge = false): static
  {
    $ratio = $height / $this->getSourceHeight();
    $width = $this->getSourceWidth() * $ratio;

    $this->resize($width, $height, $allowEnlarge);

    return $this;
  }

  /**
   * @param int $width
   * @param bool $allowEnlarge
   * @return $this
   */
  public function resizeToWidth(int $width, bool $allowEnlarge = false): static
  {
    $ratio = $width / $this->getSourceWidth();
    $height = intval($this->getSourceHeight() * $ratio);

    $this->resize($width, $height, $allowEnlarge);

    return $this;
  }

  /**
   * @param int $maxWidth
   * @param int $maxHeight
   * @param bool $allowEnlarge
   * @return $this
   */
  public function resizeToBestFit(
    int  $maxWidth,
    int  $maxHeight,
    bool $allowEnlarge = false
  ): static
  {
    if ($this->getSourceWidth() <= $maxWidth
      && $this->getSourceHeight() <= $maxHeight
      && $allowEnlarge === false) {
      return $this;
    }

    $ratio = $this->getSourceHeight() / $this->getSourceWidth();
    $width = $maxWidth;
    $height = $width * $ratio;

    if ($height > $maxHeight) {
      $height = $maxHeight;
      $width = $height / $ratio;
    }

    return $this->resize($width, $height, $allowEnlarge);
  }

  /**
   * @param int $scale
   * @return $this
   */
  public function scale(int $scale): static
  {
    $width = $this->getSourceWidth() * $scale / 100;
    $height = $this->getSourceHeight() * $scale / 100;

    $this->resize($width, $height, true);

    return $this;
  }

  /**
   * @param int $width
   * @param int $height
   * @param bool $allowEnlarge
   * @return $this
   */
  public function resize(int $width, int $height, bool $allowEnlarge = false): static
  {
    if (!$allowEnlarge) {
      // if the user hasn't explicitly allowed enlarging,
      // but either of the dimensions are larger than the original,
      // then just use original dimensions - this logic may need rethinking

      if ($width > $this->getSourceWidth() || $height > $this->getSourceHeight()) {
        $width = $this->getSourceWidth();
        $height = $this->getSourceHeight();
      }
    }

    $this->sourceX = 0;
    $this->sourceY = 0;

    $this->destW = $width;
    $this->destH = $height;

    $this->sourceW = $this->getSourceWidth();
    $this->sourceH = $this->getSourceHeight();

    return $this;
  }

  /**
   * Crops image according to the given width, height and crop position
   *
   * @param integer $width
   * @param integer $height
   * @param boolean $allowEnlarge
   * @param integer $position
   * @return static
   */
  public function crop(
    int  $width,
    int  $height,
    bool $allowEnlarge = false,
    int  $position = self::CROP_CENTER
  ): static
  {
    if (!$allowEnlarge) {
      // this logic is slightly different to resize(),
      // it will only reset dimensions to the original
      // if that particular dimension is larger

      if ($width > $this->getSourceWidth()) {
        $width = $this->getSourceWidth();
      }

      if ($height > $this->getSourceHeight()) {
        $height = $this->getSourceHeight();
      }
    }

    $ratioSource = $this->getSourceWidth() / $this->getSourceHeight();
    $ratioDest = $width / $height;

    if ($ratioDest < $ratioSource) {
      $this->resizeToHeight($height, $allowEnlarge);

      $excess_width = ($this->getDestWidth() - $width) / $this->getDestWidth() * $this->getSourceWidth();

      $this->sourceW = $this->getSourceWidth() - $excess_width;
      $this->sourceX = $this->getCropPosition($excess_width, $position);

      $this->destW = $width;
    } else {
      $this->resizeToWidth($width, $allowEnlarge);

      $excessHeight = intval(($this->getDestHeight() - $height) / $this->getDestHeight() * $this->getSourceHeight());

      $this->sourceH = $this->getSourceHeight() - $excessHeight;
      $this->sourceY = $this->getCropPosition($excessHeight, $position);

      $this->destH = $height;
    }

    return $this;
  }

  /**
   * @param int $width
   * @param int $height
   * @param int|null $x
   * @param int|null $y
   * @return $this
   */
  public function freeCrop(int $width, int $height, int $x = null, int $y = null): static
  {
    if (!$x || !$y) {
      return $this->crop($width, $height);
    }

    $this->sourceX = $x;
    $this->sourceY = $y;

    if ($width > $this->getSourceWidth() - $x) {
      $this->sourceW = $this->getSourceWidth() - $x;

    } else {
      $this->sourceW = $width;
    }

    if ($height > $this->getSourceHeight() - $y) {
      $this->sourceH = $this->getSourceHeight() - $y;

    } else {
      $this->sourceH = $height;
    }

    $this->destW = $width;
    $this->destH = $height;

    return $this;
  }

  /**
   * @return int
   */
  public function getSourceWidth(): int
  {
    return $this->originalW;
  }

  /**
   * @return int
   */
  public function getSourceHeight(): int
  {
    return $this->originalH;
  }

  /**
   * @return int
   */
  public function getDestWidth(): int
  {
    return $this->destW;
  }

  /**
   * @return int
   */
  public function getDestHeight(): int
  {
    return $this->destH;
  }

  /**
   * @param int $expectedSize
   * @param int $position
   * @return int
   */
  protected function getCropPosition(int $expectedSize, int $position = self::CROP_CENTER): int
  {
    $size = 0;
    switch ($position) {

      case self::CROP_BOTTOM:
      case self::CROP_RIGHT:
        $size = $expectedSize;
        break;

      case self::CROP_CENTER:
      case self::CROP_CENTRE:
        $size = $expectedSize / 2;
        break;

      case self::CROP_TOP_CENTER:
        $size = $expectedSize / 4;
        break;
    }
    return $size;
  }

  /**
   * @param mixed|resource $image
   * @param int $mode
   * @return void
   */
  public function imageFlip(mixed $image, int $mode): void
  {
    switch ($mode) {
      case self::IMG_FLIP_HORIZONTAL:
      {
        $max_x = imagesx($image) - 1;
        $half_x = $max_x / 2;
        $sy = imagesy($image);
        $temp_image = imageistruecolor($image) ? imagecreatetruecolor(1, $sy) : imagecreate(1, $sy);
        for ($x = 0; $x < $half_x; ++$x) {
          imagecopy($temp_image, $image, 0, 0, $x, 0, 1, $sy);
          imagecopy($image, $image, $x, 0, $max_x - $x, 0, 1, $sy);
          imagecopy($image, $temp_image, $max_x - $x, 0, 0, 0, 1, $sy);
        }
        break;
      }
      case self::IMG_FLIP_VERTICAL:
      {
        $sx = imagesx($image);
        $max_y = imagesy($image) - 1;
        $half_y = $max_y / 2;
        $temp_image = imageistruecolor($image) ? imagecreatetruecolor($sx, 1) : imagecreate($sx, 1);
        for ($y = 0; $y < $half_y; ++$y) {
          imagecopy($temp_image, $image, 0, 0, 0, $y, $sx, 1);
          imagecopy($image, $image, 0, $y, 0, $max_y - $y, $sx, 1);
          imagecopy($image, $temp_image, 0, $max_y - $y, 0, 0, $sx, 1);
        }
        break;
      }
      case self::IMG_FLIP_BOTH:
      {
        $sx = imagesx($image);
        $sy = imagesy($image);
        $temp_image = imagerotate($image, 180, 0);
        imagecopy($image, $temp_image, 0, 0, 0, 0, $sx, $sy);
        break;
      }
      default:
        return;
    }
    imagedestroy($temp_image);
  }

  /**
   * @param bool $enable
   * @return $this
   */
  public function gamma(bool $enable = true): static
  {
    $this->gammaCorrect = $enable;
    return $this;
  }
}
