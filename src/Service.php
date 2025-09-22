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
     * Обработчик входящих обновлений Telegram
     *
     * - Обрабатывает нажатия inline-кнопок и переключает политику устройств
     * - Формирует inline-клавиатуру с любимыми устройствами
     * - Добавляет эмодзи для отображения текущей политики
     *
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
     *  Обрабатывает нажатие на inline-кнопку.
     *
     *  Берёт chat_id и message_id из callbackQuery, меняет политику устройства
     *  через Keenetic API, перерисовывает inline-клавиатуру и отправляет ответ пользователю.
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

        // Формируем кнопки заново с обновлёнными статусами
        $buttons = [];

        foreach ($favDevices as $macKey => $device) {
            $policy = $macKey === $mac ? $newPolicy : $device['policy'];
            $emoji = $policy === 'Policy0' ? '🟢' : '⚪';
            $buttons[] = [
                new InlineKeyboardButton(
                    text: "{$device['name']} ($emoji)",
                    callbackData: "{$macKey}"
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
     *  Обрабатывает обычное сообщение пользователя.
     *
     *  Если есть предыдущее сообщение бота для этого chat_id, удаляет его.
     *  Затем отправляет новое сообщение (с inline-кнопками или текст "Вызови /start")
     *  и сохраняет message_id в storage для дальнейшего удаления.
     * @param object $message
     * @param array $favDevices
     * @return void
     */
    private function handleMessage(object $message, array $favDevices): void
    {
        $chatId = $message->chat->id;
        $messageText = $message->text;
        $storage = $this->storage->loadStorage();
        $lastMessageId = $storage['users'][$chatId]['last_message_id'] ?? null;

        if ($lastMessageId) {
            $this->tg->deleteMessage($chatId, $lastMessageId);
        }

        if ($messageText === '/start') {
            $buttons = $this->getDeviceButtons($favDevices);

            $newMessageId = $this->tg->sendMessage(
                chatId: $chatId,
                text: 'Выберите устройство:',
                replyMarkup: new InlineKeyboardMarkup($buttons)
            );
            $this->storage->updateStorage($chatId, ['last_message_id' => $newMessageId]);
        } else {
            $newMessageId = $this->tg->sendMessage(
                chatId: $chatId,
                text: 'Вызови /start'
            );
            $this->storage->updateStorage($chatId, ['last_message_id' => $newMessageId]);
        }
    }


    private function getDeviceButtons(array $favDevices): array
    {
        $buttons = [];

        foreach ($favDevices as $mac => $device) {
            $emoji = $device['policy'] === 'Policy0' ? '🟢' : '⚪';
            $buttons[] = [
                new InlineKeyboardButton(
                    text: "{$device['name']} ($emoji)",
                    callbackData: (string) $mac
                )
            ];
        }

        return $buttons;
    }
}