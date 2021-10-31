<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Bitrix24\Bitrix24;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$log = new Logger('MainLog');
$log->pushHandler(new StreamHandler(__DIR__ . '/log/error.log', Logger::ERROR));

if ($_ENV['DEBUG']) {
	$log->pushHandler(new StreamHandler(__DIR__ . '/log/debug.log', Logger::DEBUG));
}

try {
	$obB24App = new Bitrix24(false, $log);

	$obB24App->setDomain($_ENV['BITRIX24_DOMAIN']);
	$obB24App->setWebhookUsage(true);
	$obB24App->setWebhookSecret($_ENV['BITRIX24_WEBHOOK_SECRET']);
} catch (\Exception $e) {
    $log->error($e->getMessage());
	die();
}
