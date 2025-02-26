<?php

require __DIR__ . '/../vendor/autoload.php';

header('Content-type: application/json');

echo json_encode(
  [
    'feeds' => [
      'employee' => '/data/employee-feed.csv',
      'employee-positions' => '/data/employee-position-feed.csv',
    ],
  ]
);
