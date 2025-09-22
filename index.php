<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Service;
use App\KeeneticAPI;
use App\Telegram;
use Dotenv\Dotenv;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

$baseUri = "https://" . getenv('ROUTE_BASEURI');
$login = getenv('ROUTE_LOGIN');
$password = getenv('ROUTE_PASS');
$tgToken = getenv('TOKEN_TELEGRAM');

$service = new Service(
    new KeeneticAPI($baseUri, $login, $password),
    new Telegram($tgToken)
);

$service->handle();
