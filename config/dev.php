<?php

return [
  'env' => 'dev',
  'air' => [
    'exception' => true,
    'phpIni' => [
      'display_errors' => '1',
    ],
    'startup' => [
      'error_reporting' => E_ALL,
    ],
  ],
];