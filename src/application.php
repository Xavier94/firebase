#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Fire\Console\ReadCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ReadCommand());
$application->run();
