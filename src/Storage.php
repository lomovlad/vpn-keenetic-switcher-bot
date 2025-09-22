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

    /**
     * Загружает хранилище данных пользователей из JSON-файла.
     * @param string $file
     * @return array{users: array<int, array{last_message_id?: int}>}
     */
    public function loadStorage(string $file = 'storage.json'): array
    {
        if (!file_exists($file)) {

            return ['users' => []];
        }

        $data = json_decode(file_get_contents($file), true);

        return $data ?: ['users' => []];
    }

    /**
     * Сохраняет данные пользователей в JSON-файл.
     * @param array $data Массив данных для сохранения
     * @param string $file Путь к файлу хранения данных (по умолчанию 'storage.json')
     * @return void
     */
    public function saveStorage(array $data, string $file = 'storage.json'): void
    {
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Обновляет данные пользователя в storage и сохраняет файл.
     * @param int $chatId
     * @param array $newData
     * @param string $file
     * @return void
     */
    public function updateStorage(int $chatId, array $newData, string $file = 'storage.json'): void
    {
        $storage = $this->loadStorage($file);

        if (!isset($storage['users'][$chatId])) {
            $storage['users'][$chatId] = [];
        }

        $storage['users'][$chatId] = array_merge(
            $storage['users'][$chatId],
            $newData
        );

        $this->saveStorage($storage, $file);
    }
}