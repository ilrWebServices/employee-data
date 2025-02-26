<?php

require __DIR__ . '/../vendor/autoload.php';

header('Content-type: application/json');

echo json_encode(
  [
    'feeds' => [
      'employee' => 'tbd',
      'employee-positions' => 'tbd',
    ],
  ]
);
