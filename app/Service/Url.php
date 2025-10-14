<?php

declare(strict_types=1);

namespace App\Service;

use Air\Core\Front;
use Exception;

class Url
{
  protected static function parseModifiedImageUrl(string $url, string $storageRoot): array
  {
    $res = [
      'width' => null,
      'height' => null,
      'quality' => null,
      'format' => null,
      'source' => null,
    ];

    $pi = pathinfo($url);
    $dirname = $pi['dirname'] ?? '/storage';
    $filename = $pi['filename'] ?? '';
    $ext = isset($pi['extension']) ? strtolower($pi['extension']) : null;

    $relativePath = preg_replace('#^/storage#', '', $url);
    $res['dest'] = rtrim($storageRoot, '/') . $relativePath;

    if (!preg_match('/_mod(?:_.+)?$/i', $filename)) {
      $originalBase = $filename;

    } else {
      if (!preg_match('/^(?P<base>.+?)_mod(?:_(?P<mods>(?:[whq]\d+)(?:_(?:[whq]\d+))*))?$/i', $filename, $m)) {
        $originalBase = preg_replace('/_mod.*$/i', '', $filename);

      } else {
        $originalBase = $m['base'];

        if (!empty($m['mods'])) {
          foreach (explode('_', $m['mods']) as $mod) {

            if (preg_match('/^w(\d+)$/i', $mod, $mm)) {
              $res['width'] = (int)$mm[1];

            } elseif (preg_match('/^h(\d+)$/i', $mod, $mm)) {
              $res['height'] = (int)$mm[1];

            } elseif (preg_match('/^q(\d+)$/i', $mod, $mm)) {
              $res['quality'] = (int)$mm[1];
            }
          }
        }
      }
    }

    $relativeDir = preg_replace('#^/storage#', '', $dirname);
    $fsDir = rtrim($storageRoot, '/') . $relativeDir;

    $possibleExts = ['jpg', 'jpeg', 'png', 'webp', 'avif'];
    $foundSource = null;
    $sourceExt = null;

    foreach ($possibleExts as $e) {
      $candidate = $fsDir . '/' . $originalBase . '.' . $e;
      if (file_exists($candidate)) {
        $foundSource = $candidate;
        $sourceExt = $e;
        break;
      }
    }

    if (!$foundSource) {
      throw new Exception();
    }

    $res['format'] = ($ext && $ext !== $sourceExt) ? $ext : null;
    $res['source'] = $foundSource;

    return $res;
  }

  public static function normalizeUrl(string $url): string
  {
    $shortUrl = array_values(array_filter(explode('/', $url)));
    return '/' . implode('/', $shortUrl);
  }

  public static function propagateImage(): string
  {
    $front = Front::getInstance();

    $params = self::parseModifiedImageUrl(
      self::normalizeUrl($front->getRequest()->getUri()),
      realpath($front->getConfig()['fs']['path'])
    );

    $image = new ImageProcessor((string)$params['source']);

    if ($params['width'] && $params['height']) {
      $image->cropAndResize((int)$params['width'], (int)$params['height']);
    } elseif ($params['width'] || $params['height']) {
      $image->resizeToLongSide((int)($params['width'] ?? $params['height']));
    }

    $image->save((string)$params['dest'], quality: (int)$params['quality']);

    return Front::getInstance()->getConfig()['fs']['host'] . $front->getRequest()->getUri();
  }
}