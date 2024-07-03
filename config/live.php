<?php

return [
  'env' => 'live',
  'air' => [
    'exception' => false,
    'phpIni' => [
      'display_errors' => '0',
    ],
    'loader' => [
      'path' => realpath(dirname(__FILE__)) . '/../app',
      'namespace' => 'App',
    ],
  ],
  'fs' => [
    'folder' => 'storage',
    'path' => realpath(dirname(__FILE__)) . '/../www/storage',
    'url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/storage',
    'host' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'],
    'thumbnails' => '__thumbnails'
  ],
  'key' => 'TheBlueCurasaoInAirCMSOfTheNight'
];
