<?php

require_once __DIR__ . '/../vendor/autoload.php';

$env = getenv('AIR_ENV') ?: 'dev';

if ($env === 'dev') {
  $config = array_replace_recursive(
    require_once '../config/live.php',
    require_once '../config/dev.php'
  );
} else {
  $config = require_once '../config/live.php';
}

echo \Air\Core\Front::getInstance($config)
  ->bootstrap()
  ->run();