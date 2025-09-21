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
        private TelegramBotApi $botApi
    )
    {
        $this->keenetic->auth();
    }

    private function getTelegramUpdate(): ?Update
    {
        $input = file_get_contents('php://input');
        try {
            return Update::fromJson($input);
        } catch (TelegramParseResultException $e) {
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
        $update = $this->getTelegramUpdate();

        if ($update === null) {
            return;
        }

        $devices = $this->keenetic->getDevices();
        $favDevices = $this->keenetic->getFavDevices($devices);

        if ($update->callbackQuery !== null) {
            $this->handleCallbackQuery($update->callbackQuery, $favDevices);
            return;
        }

        if ($update->message !== null) {
            $this->handleMessage($update->message, $favDevices);
        }
    }

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

        $this->botApi->editMessageReplyMarkup(
            chatId: $chatId,
            messageId: $messageId,
            replyMarkup: new InlineKeyboardMarkup($buttons)
        );

        $alert = $success
            ? "Политика для устройства $mac изменена на $newPolicy"
            : "Не удалось изменить политику";

        $this->botApi->answerCallbackQuery(
            callbackQueryId: $callbackQuery->id,
            text: $alert,
            showAlert: true
        );
    }

    private function handleMessage(object $message, array $favDevices): void
    {
        $chatId = $message->chat->id;
        $messageText = $message->text;

        if ($messageText === '/start') {
            $buttons = [];
            foreach ($favDevices as $mac => $device) {
                $emoji = $device['policy'] === 'Policy0' ? '🟢' : '⚪';
                $buttons[] = [
                    new InlineKeyboardButton(
                        text: "{$device['name']} ($emoji)",
                        callbackData: "{$mac}"
                    )
                ];
            }

            $this->botApi->sendMessage(
                chatId: $chatId,
                text: 'Выберите устройство:',
                replyMarkup: new InlineKeyboardMarkup($buttons)
            );
        } else {
            $this->botApi->sendMessage(
                chatId: $chatId,
                text: 'Вызови /start'
            );
        }
    }

}