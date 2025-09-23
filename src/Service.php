<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Exception\GuzzleException;
use Vjik\TelegramBot\Api\ParseResult\TelegramParseResultException;
use Vjik\TelegramBot\Api\TelegramBotApi;
use Vjik\TelegramBot\Api\Type\InlineKeyboardButton;
use Vjik\TelegramBot\Api\Type\InlineKeyboardMarkup;
use Vjik\TelegramBot\Api\Type\Update\Update;

class Service
{
    public function __construct(
        private KeeneticApi $keenetic,
        private TelegramBotApi $tg,
        private StorageInterface $storage
    )
    {
        $this->keenetic->auth();
    }

    /**
     * Получает входящий апдейт из Telegram.
     * @return Update|null
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

    /**
     * Обрабатывает входящий апдейт (сообщение или callback).
     * @return void
     * @throws GuzzleException
     */
    public function handle(): void
    {
        $update = $this->getUpdate();

        if ($update === null) {

            return;
        }

        $devices = $this->keenetic->getDevices();
        $favDevices = $this->keenetic->getFavDevices($devices);

        if ($update->callbackQuery !== null) {
            $this->handleCallbackQuery($update->callbackQuery, $favDevices);

            return;
        }

        $this->handleMessage($update->message, $favDevices);
    }

    /**
     * Обработка callback-запроса от Telegram (нажатие на inline-кнопку).
     * @param object $callbackQuery
     * @param array $favDevices
     * @return void
     * @throws GuzzleException
     */
    private function handleCallbackQuery(object $callbackQuery, array $favDevices): void
    {
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->messageId;

        $mac = $callbackQuery->data;
        $currentPolicy = $favDevices[$mac]['policy'] ?? 'default';
        $newPolicy = $currentPolicy === 'Policy0' ? 'default' : 'Policy0';
        $success = $this->keenetic->setPolicyDevice($mac, $newPolicy);
        $buttons = [];

        foreach ($favDevices as $macKey => $device) {
            $policy = $macKey === $mac ? $newPolicy : $device['policy'];
            $emoji = $policy === 'Policy0' ? '🟢' : '⚪';
            $buttons[] = [
                new InlineKeyboardButton(
                    text: "{$device['name']} ($emoji)",
                    callbackData: (string)$macKey
                )
            ];
        }

        $this->tg->editMessageReplyMarkup(
            chatId: $chatId,
            messageId: $messageId,
            replyMarkup: new InlineKeyboardMarkup($buttons)
        );

        $alert = $success
            ? "Политика для устройства $mac изменена на $newPolicy"
            : "Не удалось изменить политику";

        $this->tg->answerCallbackQuery(
            callbackQueryId: $callbackQuery->id,
            text: $alert,
            showAlert: true
        );
    }

    /**
     * Обработка входящего сообщения пользователя.
     * @param object $message
     * @param array $favDevices
     * @return void
     */
    private function handleMessage(object $message, array $favDevices): void
    {
        $chatId = $message->chat->id;
        $text = $message->text ?? '';

        // Получаем ID последнего сообщения бота
        $storage = $this->storage->loadStorage();
        $lastMessageId = $storage['users'][$chatId]['last_message_id'] ?? null;

        // Удаляем предыдущее сообщение, если есть
        if ($lastMessageId) {
            $this->tg->deleteMessage($chatId, (int)$lastMessageId);
        }

        // Отправляем новое сообщение
        if ($text === '/start') {
            $buttons = $this->getDeviceButtons($favDevices);
            $response = $this->tg->sendMessage(
                chatId: $chatId,
                text: 'Выберите устройство:',
                replyMarkup: new InlineKeyboardMarkup($buttons)
            );
        } else {
            $response = $this->tg->sendMessage(
                chatId: $chatId,
                text: 'Вызови /start'
            );
        }

        // Берём ID из объекта Message
        $newMessageId = $response->messageId;

        // Сохраняем ID нового сообщения
        $this->storage->updateStorage($chatId, ['last_message_id' => $newMessageId]);
    }

    /**
     * Генерирует кнопки для устройств.
     * @param array $favDevices
     * @return array
     */
    private function getDeviceButtons(array $favDevices): array
    {
        $buttons = [];

        foreach ($favDevices as $mac => $device) {
            $emoji = $device['policy'] === 'Policy0' ? '🟢' : '⚪';
            $buttons[] = [
                new InlineKeyboardButton(
                    text: "{$device['name']} ($emoji)",
                    callbackData: (string)$mac
                )
            ];
        }

        return $buttons;
    }
}