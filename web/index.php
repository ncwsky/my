<?php

define('APP_PATH', __DIR__.'/../app');
define('COMMON', __DIR__.'/../common');
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../conf.php";
require __DIR__ . "/../vendor/myphps/myphp/base.php";

myphp::Run();
