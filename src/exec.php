<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

$command = '/usr/local/php5.6/bin/php /home/philiberer/www/firebase/src/application.php fire:read -p /home/philiberer/www/firebase/src/config.ini -vv';
//$command = '/usr/local/php5.6/bin/php -v';
//$command = 'pwd'; // /home/philiberer/www/firebase/src

$process = new Process($command);
$process->run();

if (!$process->isSuccessful()) {
	echo 'ERREUR';
	return 1;
}

$file = $process->getOutput();

file_put_contents('cron-' . date('Y-m-d') . '.log', "\n" . $file, FILE_APPEND);

return 0;
