<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use http\Exception\RuntimeException;


class KeeneticAPI
{

    private string $baseUri;
    private string $login;
    private string $password;
    private CookieJar $jar;
    private Client $httpClient;
    private array $favDevicesMacs;

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
        $this->favDevicesMacs = [
            'ce:7b:6f:65:fd:6e',
            '46:36:fe:b5:de:d8',
            '90:de:80:21:c7:bc',
            'd8:43:ae:0f:45:5d',
            '8c:c8:4b:d6:0c:eb',
            '3e:ad:a3:77:51:0d'
        ];
    }

    /**
     * Авторизация двойным запросом
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
                throw new RuntimeException('Не удалось получить challenge от роутера');
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
     * Установка политики
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
            throw new RuntimeException('Ошибка при применении политики: ' . $e->getMessage());
        }
    }

    /**
     * Получить список устройств
     * @return array ассоциативный масиив [mac => [name, policy]]
     * @throws GuzzleException
     */
    public function getDevices(): array
    {
        try {
            $response = $this->httpClient->get('rci/ip/hotspot/host');
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return [];
            }

            $result = [];

            foreach ($data as $device) {
                $mac = $device['mac'] ?? null;
                $policy = $device['policy'] ?? 'default';
                $name = $device['name'] ?? 'unknown';

                if ($mac && in_array($mac, $this->favDevicesMacs, true)) {
                    $result[$mac] = [
                        'name' => $name,
                        'policy' => $policy,
                    ];
                }
            }
        } catch (RequestException $e) {
            throw new RuntimeException('Ошибка при применении политики: ' . $e->getMessage());
        }

        return $result;
    }
}