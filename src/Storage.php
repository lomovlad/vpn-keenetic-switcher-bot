<?php

declare(strict_types=1);

namespace App;

class Storage implements StorageInterface
{
    public const array FAV_DEVICES_MACS = [
        'ce:7b:6f:65:fd:6e',
        '46:36:fe:b5:de:d8',
        '90:de:80:21:c7:bc',
        'd8:43:ae:0f:45:5d',
        '8c:c8:4b:d6:0c:eb',
        '3e:ad:a3:77:51:0d'
    ];

    private string $file;

    public function __construct(string $file = __DIR__ . '/../storage.json')
    {
        $this->file = $file;
    }

    /**
     *  Загружает JSON-файл
     * @return array[]
     */
    private function loadStorage(): array
    {
        if (!file_exists($this->file)) {
            return ['users' => []];
        }

        $data = json_decode(file_get_contents($this->file), true);

        return $data ?: ['users' => []];
    }

    /**
     * Сохраняет массив в JSON-файл
     * @param array $data
     * @return void
     */
    private function saveStorage(array $data): void
    {
        file_put_contents(
            $this->file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @return array|string[]
     */
    public function getFavMacs(): array
    {
        return self::FAV_DEVICES_MACS;
    }

    /**
     * @param int $chatId
     * @return int|null
     */
    public function getLastMessageId(int $chatId): ?int
    {
        $storage = $this->loadStorage();

        return $storage['users'][$chatId]['last_message_id'] ?? null;
    }

    /**
     * @param int $chatId
     * @param int $messageId
     * @return void
     */
    public function setLastMessageId(int $chatId, int $messageId): void
    {
        $storage = $this->loadStorage();

        if (!isset($storage['users'][$chatId])) {
            $storage['users'][$chatId] = [];
        }

        $storage['users'][$chatId]['last_message_id'] = $messageId;
        $this->saveStorage($storage);
    }
}
