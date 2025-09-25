<?php

declare(strict_types=1);

namespace App;

class Storage implements StorageInterface
{
    private string $file = __DIR__ . '/../storage.json';

    /**
     * Загружает JSON-файл
     * @return array{users: array, fav_macs: array}
     */
    private function loadStorage(): array
    {
        $default = ['users' => [], 'fav_macs' => []];

        if (!file_exists($this->file)) {
            return $default;
        }

        $data = json_decode(file_get_contents($this->file), true);

        return $data ?: $default;
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
     * Получить избранные mac устройств
     * @return array
     */
    public function getFavMacs(): array
    {
        $storage = $this->loadStorage();

        return $storage['fav_macs'] ?? [];
    }

    /**
     * Получить ID последнего сообщения бота
     * @param int $chatId
     * @return int|null
     */
    public function getLastMessageId(int $chatId): ?int
    {
        $storage = $this->loadStorage();

        return $storage['users'][$chatId]['last_message_id'] ?? null;
    }

    /**
     * Сохранить ID последнего сообщения бота
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
