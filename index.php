<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Dotenv\Dotenv;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

$dotenv = Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

$baseUri = "https://" . getenv('ROUTE_BASEURI');
$login = getenv('ROUTE_LOGIN');
$password = getenv('ROUTE_PASS');

# Создание cookie-jar и клиента Guzzle
$jar = new CookieJar();
$client = new Client([
    'base_uri' => $baseUri,
    'cookies' => $jar,
    'verify' => false,
    'http_errors' => false
]);

# Первый запрос  GET /auth для получения challenge
try {
    $response = $client->get('auth');
    $headers = $response->getHeaders();

    $realm = $headers['X-NDM-Realm'][0] ?? '';
    $challenge = $headers['X-NDM-Challenge'][0] ?? '';

    if (!$realm || !$challenge) {
        die("Не удалось получить challenge\n");
    }
} catch (GuzzleException $e) {
    echo "Ошибка при GET: " . $e->getMessage();
}

# Вычисление хешей
$md5 = md5("$login:$realm:$password");
$sha = hash('sha256', $challenge . $md5);

try {
#  Второй запрос POST /auth с логином и хешем
    $response = $client->post('auth', [
        'json' => [
            'login' => $login,
            'password' => $sha
        ],
        'headers' => [
            'Content-Type' => 'application/json'
        ],
    ]);

    $statusCode = $response->getStatusCode();
    $body = $response->getBody()->getContents();
} catch (GuzzleException $e) {
    echo "Ошибка при POST: " . $e->getMessage();
}

# Актуализируем состояние устройств от роутера
function getDevicesPoliciesRout(Client $httpCli, array $macList): array
{
    $result = [];

    try {
        $response = $httpCli->get('rci/ip/hotspot/host');
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return array_fill_keys($macList, 'unknown');
        }

        // Проходим по массиву роутера
        foreach ($data as $device) {
            $mac = strtolower($device['mac'] ?? '');
            $policy = $device['policy'] ?? 'default';

            if ($mac && in_array($mac, array_map('strtolower', $macList), true)) {
                $result[$mac] = $policy;
            }
        }

        // Для тех MAC, которых нет в ответе, ставим default
        foreach ($macList as $mac) {
            $macLower = strtolower($mac);
            if (!isset($result[$macLower])) {
                $result[$macLower] = 'default';
            }
        }

    } catch (GuzzleException $e) {
        echo "Ошибка при getDevicesPoliciesRout: " . $e->getMessage();
        $result = array_fill_keys($macList, 'default');
    }

    return $result;
}

# База данных
$host = getenv('DB_HOST');
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$charset = getenv('DB_CHARSET');
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "Ошибка подключения: " . $e->getMessage();
}

# Получаем данные из БД
function getDevicesFromDb(PDO $db): array
{
    $stmt = $db->query("SELECT id, name, mac, policy FROM devices");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

# Обновление политики в БД
function updateDbDevicePolicy(PDO $db, string $mac, string $policy): bool
{
    $stmt = $db->prepare('UPDATE `devices` SET `policy` = :policy, `updated_at` = NOW() WHERE `mac` = :mac');

    return $stmt->execute([
        'mac' => $mac,
        'policy' => $policy
    ]);
}

function syncDevicesPolicies(PDO $db, Client $httpCli): bool
{
    # 1. Получаем список устройств из БД
    $devices = getDevicesFromDb($db);
    $macList = array_column($devices, 'mac');

    # 2. Получаем состояния из роутера
    $policies = getDevicesPoliciesRout($httpCli, $macList);

    # 3. Обновляем БД
    foreach ($policies as $mac => $policy) {
        updateDbDevicePolicy($db, $mac, $policy);
    }

    return true;
}

function setPolicyToRout(Client $httpCli, string $mac, string $newPolicy): void
{
    try {
        $httpCli->post('rci/ip/hotspot/host', [
            'json' => [
                'mac' => $mac,
                'permit' => true,
                'policy' => $newPolicy === 'default' ? (object)['no' => true] : $newPolicy
            ]
        ]);

        // Сохранение конфигурации
        $httpCli->post('rci/system/configuration/save', [
            'json' => (object)[] // пустой объект
        ]);

    } catch (GuzzleException $e) {
        echo "Ошибка при запросе: " . $e->getMessage() . "\n";
    }
}

function togglePolicy(PDO $db, Client $httpCli, string $mac): string
{
    $stmt = $db->prepare("SELECT policy FROM devices WHERE mac = :mac");
    $stmt->execute(['mac' => $mac]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentPolicy = $device['policy'] ?? 'default';
    $newPolicy = $currentPolicy === 'Policy0' ? 'default' : 'Policy0';

    setPolicyToRout($httpCli, $mac, $newPolicy);

    $stmtUpdate = $db->prepare("UPDATE devices SET policy = :policy, updated_at = NOW() WHERE mac = :mac");
    $stmtUpdate->execute([
        'policy' => $newPolicy,
        'mac' => $mac
    ]);

    return $newPolicy;
}

# Telegram

$bot_api_key = getenv('TOKEN_TELEGRAM');
$bot_username = getenv('USERNAMEBOT_TELEGRAM');

try {
    $telegram = new Telegram($bot_api_key, $bot_username);
    $telegram->useGetUpdatesWithoutDatabase(true);
    $offset = 0;

    // Получаем все устройства один раз в локальный массив
    $devices = getDevicesFromDb($pdo);

    while (true) {
        $updates = $telegram->handleGetUpdates($offset)->getResult();

        foreach ($updates as $update) {
            $offset = $update->getUpdateId() + 1;

            $callback = $update->getCallbackQuery();
            if ($callback && str_starts_with($callback->getData(), 'toggle: ')) {
                $mac = trim(explode(':', $callback->getData(), 2)[1]);

                // Меняем политику и получаем новое значение
                $newPolicy = togglePolicy($pdo, $client, $mac);

                // Обновляем **только локальный массив**
                foreach ($devices as &$device) {
                    if ($device['mac'] === $mac) {
                        $device['policy'] = $newPolicy;
                    }
                }
                unset($device);

                // Строим клавиатуру один раз из локального массива
                $keyboard = buildDevicesKeyboard($devices);

                Request::editMessageReplyMarkup([
                    'chat_id' => $callback->getMessage()->getChat()->getId(),
                    'message_id' => $callback->getMessage()->getMessageId(),
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                ]);

                Request::answerCallbackQuery(['callback_query_id' => $callback->getId()]);
            }

            // Обработка текстовых сообщений
            $message = $update->getMessage();
            if ($message && $message->getText() !== null) {
                $chat_id = $message->getChat()->getId();
                if ($message->getText() === '/start') {
                    // Синхронизация с роутером и обновление локального массива
                    syncDevicesPolicies($pdo, $client);
                    $devices = getDevicesFromDb($pdo); // обновляем локальный массив

                    Request::sendMessage([
                        'chat_id' => $chat_id,
                        'text' => 'Устройства:',
                        'reply_markup' => json_encode(['inline_keyboard' => buildDevicesKeyboard($devices)]),
                    ]);
                }
            }
        }

        sleep(1);
    }
} catch (TelegramException $e) {
    echo $e->getMessage() . PHP_EOL;
}

// Вспомогательная функция для построения клавиатуры из готового массива устройств
function buildDevicesKeyboard(array $devices): array
{
    $keyboard = [];
    foreach ($devices as $device) {
        $policyText = $device['policy'] === 'Policy0' ? "🟢" : "⚪";
        $keyboard[] = [[
            'text' => "{$device['name']} ({$policyText})",
            'callback_data' => "toggle: {$device['mac']}",
        ]];
    }
    return $keyboard;
}

