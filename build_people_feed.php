<?php

use IlrProfilesDataFeed\Runner;

require __DIR__ . '/vendor/autoload.php';

Runner::build(output_dir: __DIR__ . '/web/data/');
