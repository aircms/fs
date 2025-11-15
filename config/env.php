<?php

use Air\Config;

return Config::defaults(
  strictRoutes: false,
  single: true,
  extensions: [
    'key' => getenv('AIR_FS_KEY'),
    'fs' => [
      'path' => realpath(dirname(__FILE__) . '/../www/storage'),
      'url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/storage',
      'host' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'],
      'thumbnail' => [
        'width' => 300,
        'height' => 180,
      ]
    ],
    'locale' => [
      'ua' => require_once 'locale/ua.php',
      'en' => require_once 'locale/en.php',
    ],
    'air' => [
      'asset' => [
        'prefix' => '/assets',
      ]
    ]
  ]
);