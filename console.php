#! /usr/bin/env php

<?php

use Scaffold\Commander;
use Symfony\Component\Console\Application;

require('vendor/autoload.php');

$app = new Application("WordPress Scaffold", "0.1");

$app->add(new Commander(new GuzzleHttp\Client));
$app->run();