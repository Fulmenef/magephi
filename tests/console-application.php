<?php

require __DIR__ . '/../config/bootstrap.php';

$kernel = new \Magephi\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);

return new \Magephi\Application($kernel);
