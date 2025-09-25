<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Service;
use App\KeeneticAPI;
use App\Storage;
use Dotenv\Dotenv;
use Vjik\TelegramBot\Api\TelegramBotApi;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

$baseUri = "https://" . getenv('ROUTE_BASEURI');
$login = getenv('ROUTE_LOGIN');
$password = getenv('ROUTE_PASS');
$tgToken = getenv('TOKEN_TELEGRAM');

new Service(
    new KeeneticAPI($baseUri, $login, $password),
    new TelegramBotApi($tgToken),
    new Storage()
)->handle();
