<?php

require_once 'vendor/autoload.php';
$config = require_once('config.php');

$bot = new DannyTheNinja\IRC\Bot($config);
$bot->run();
