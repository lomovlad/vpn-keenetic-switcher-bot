<?php

declare(strict_types=1);

namespace App;

use Vjik\TelegramBot\Api\ParseResult\TelegramParseResultException;
use Vjik\TelegramBot\Api\TelegramBotApi;
use Vjik\TelegramBot\Api\Type\InlineKeyboardMarkup;
use Vjik\TelegramBot\Api\Type\Update\Update;

class Telegram
{
    private TelegramBotApi $tgApi;

    public function __construct(string $token)
    {
        $this->tgApi = new TelegramBotApi($token);
    }

    /**
     * Получить апдейт из php://input
     */
    public function getUpdate(): ?Update
    {
        $input = file_get_contents('php://input');

        if (!$input) {

            return null;
        }

        try {

            return Update::fromJson($input);
        } catch (TelegramParseResultException) {

            return null;
        }
    }
// ================= Работа с хранилищем ===================

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
// ======================== Обертка методов работы с api Telegram ==========================

    /**
     * Редактирует inline-клавиатуру сообщения.
     * @param int $chatId
     * @param int $messageId
     * @param InlineKeyboardMarkup $markup
     * @return void
     */
    public function editMessageReplyMarkup(int $chatId, int $messageId, InlineKeyboardMarkup $markup): void
    {
        $this->tgApi->editMessageReplyMarkup(
            chatId: $chatId,
            messageId: $messageId,
            replyMarkup: $markup
        );
    }

    /**
     * Отвечает на callbackQuery пользователя.
     * @param string $callbackQueryId
     * @param string $text
     * @param bool $showAlert
     * @return void
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text, bool $showAlert = false): void
    {
        $this->tgApi->answerCallbackQuery(
            callbackQueryId: $callbackQueryId,
            text: $text,
            showAlert: $showAlert
        );
    }

    /**
     * Отправляет новое сообщение пользователю.
     * @param int $chatId
     * @param string $text
     * @param InlineKeyboardMarkup|null $replyMarkup
     * @return int
     */
    public function sendMessage(int $chatId, string $text, ?InlineKeyboardMarkup $replyMarkup = null): int
    {
        $response = $this->tgApi->sendMessage(
            chatId: $chatId,
            text: $text,
            replyMarkup: $replyMarkup
        );

        return $response->messageId;
    }

    /**
     * Удаляет сообщение пользователя или бота.
     * @param int $chatId
     * @param int $messageId
     * @return void
     */
    public function deleteMessage(int $chatId, int $messageId): void
    {
        $this->tgApi->deleteMessage(
            chatId: $chatId,
            messageId: $messageId
        );
    }
}
