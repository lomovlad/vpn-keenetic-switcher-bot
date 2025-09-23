<?php

declare(strict_types=1);

namespace App;

interface StorageInterface
{
    /**
     * Загружает хранилище данных пользователей из JSON-файла.
     * @return array{users: array<int, array{last_message_id?: int}>}
     */
    public function loadStorage(): array;

    /**
     * Обновляет данные пользователя в storage и сохраняет файл.
     * @param int $chatId
     * @param array{last_message_id?: int} $newData Массив данных для обновления, например ['last_message_id' => 123]
     * @return void
     */
    public function updateStorage(int $chatId, array $newData): void;

    /**
     * Сохраняет данные пользователей в JSON-файл.
     * @param array $data
     * @return void
     */
    public function saveStorage(array $data): void;
}