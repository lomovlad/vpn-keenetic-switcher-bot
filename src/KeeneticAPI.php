<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class KeeneticAPI
{
    private string $baseUri;
    private string $login;
    private string $password;
    private CookieJar $jar;
    private Client $httpClient;

    /**
     * @param string $baseUri
     * @param string $login
     * @param string $password
     */
    public function __construct(string $baseUri, string $login, string $password)
    {
        $this->baseUri = $baseUri;
        $this->login = $login;
        $this->password = $password;

        $this->jar = new CookieJar();
        $this->httpClient = new Client([
            'base_uri' => $this->baseUri,
            'cookies' => $this->jar,
            'verify' => false,
            'http_errors' => false
        ]);
    }

    /**
     * Авторизация двойным запросом (challenge-response)
     * @return bool
     * @throws GuzzleException
     */
    public function auth(): bool
    {
        try {
            $response = $this->httpClient->get('auth');
            $headers = $response->getHeaders();

            $realm = $headers['X-NDM-Realm'][0] ?? '';
            $challenge = $headers['X-NDM-Challenge'][0] ?? '';

            if (!$realm || !$challenge) {
                throw new \RuntimeException('Не удалось получить challenge от роутера');
            }

            $md5 = md5("{$this->login}:$realm:{$this->password}");
            $sha = hash('sha256', $challenge . $md5);

            $response = $this->httpClient->post('auth', [
                'json' => [
                    'login' => $this->login,
                    'password' => $sha
                ]
            ]);

            if ($response->getStatusCode() !== 200) {

                return false;
            }
        } catch (RequestException $e) {
            throw new RuntimeException('Ошибка при HTTP запросе' . $e->getMessage());
        }

        return $response->getStatusCode() === 200;
    }

    /**
     * Установка политики в роутер
     * @param string $mac
     * @param string $policy
     * @return bool
     * @throws GuzzleException
     */
    public function setPolicyDevice(string $mac, string $policy): bool
    {
        try {
            $this->httpClient->post('rci/ip/hotspot/host', [
                'json' => [
                    'mac' => $mac,
                    'permit' => true,
                    'policy' => $policy === 'default' ? (object)['no' => true] : $policy
                ]
            ]);

            // Сохранение конфигурации
            $this->httpClient->post('rci/system/configuration/save', [
                'json' => (object)[] // пустой объект
            ]);

            return true;
        } catch (RequestException $e) {
            throw new \RuntimeException('Ошибка при применении политики: ' . $e->getMessage());
        }
    }

    /**
     * Получить полный список устройств из роутера
     * @return array<string, array{name: string, policy: string}>
     * @throws GuzzleException
     */
    public function getDevices(): array
    {
        $result = [];

        try {
            // 1. Получаем список устройств (имя + MAC)
            $responseNames = $this->httpClient->post('rci/', [
                'json' => [
                    "show" => [
                        "ip" => [
                            "hotspot" => (object)[]
                        ]
                    ]
                ]
            ]);
            $namesData = json_decode($responseNames->getBody()->getContents(), true);

            $hostsNames = $namesData[0]['show']['ip']['hotspot']['host']
                ?? $namesData['show']['ip']['hotspot']['host']
                ?? [];

            // Собираем массив MAC => name
            $namesByMac = [];

            foreach ($hostsNames as $host) {
                $mac = $host['mac'] ?? null;
                $name = $host['name'] ?? 'unknown';
                if ($mac) {
                    $namesByMac[$mac] = $name;
                }
            }

            // 2. Получаем список устройств с политикой
            $responsePolicy = $this->httpClient->post('rci/', [
                'json' => [
                    "show" => [
                        "sc" => [
                            "ip" => [
                                "hotspot" => (object)[]
                            ]
                        ]
                    ]
                ]
            ]);
            $policyData = json_decode($responsePolicy->getBody()->getContents(), true);

            $hostsPolicy = $policyData['show']['sc']['ip']['hotspot']['host'] ?? [];

            // 3. Формируем единый массив MAC => [name, policy]
            foreach ($hostsPolicy as $host) {
                $mac = $host['mac'] ?? null;
                if (!$mac) continue;

                $result[$mac] = [
                    'name' => $namesByMac[$mac] ?? 'unknown',
                    'policy' => $host['policy'] ?? 'default',
                ];
            }

        } catch (\Exception $e) {
            throw new \RuntimeException('Ошибка при получении устройств: ' . $e->getMessage());
        }

        return $result;
    }
}
