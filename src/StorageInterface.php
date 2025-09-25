<?php

declare(strict_types=1);

namespace App;

interface StorageInterface
{
    /**
     * Получает ID последнего сообщения бота для указанного пользователя.
     * @param int $chatId
     * @return int|null
     */
    public function getLastMessageId(int $chatId): ?int;

    /**
     * Возвращает список MAC-адресов избранных устройств.
     * @return array
     */
    public function getFavMacs(): array;

    /**
     * Сохраняет ID последнего сообщения бота для указанного пользователя.
     * @param int $chatId
     * @param int $messageId
     * @return void
     */
    public function setLastMessageId(int $chatId, int $messageId): void;
}
