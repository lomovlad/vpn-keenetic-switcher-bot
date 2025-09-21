<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Service;
use App\KeeneticAPI;
use Vjik\TelegramBot\Api\TelegramBotApi;

use Dotenv\Dotenv;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

$baseUri = "https://" . getenv('ROUTE_BASEURI');
$login = getenv('ROUTE_LOGIN');
$password = getenv('ROUTE_PASS');
$tgToken = getenv('TOKEN_TELEGRAM');

$service = new Service(
    new KeeneticAPI($baseUri, $login, $password),
    new TelegramBotApi($tgToken)
);

$service->handle();

//https://api.telegram.org/bot8464832937:AAHTrV3e7Jhc452eo2QwAHyKmBFevUq32QY/setWebhook?url=https://c405ea8220fa92ea17957d46333c6d14.serveo.net
