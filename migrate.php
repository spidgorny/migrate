<?php

use spidgorny\migrate\Migrate;
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/src/functions.php';
$id = new Migrate();
$id->run();
