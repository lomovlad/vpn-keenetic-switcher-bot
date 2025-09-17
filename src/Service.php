<?php

declare(strict_types=1);

namespace App;

use Vjik\TelegramBot\Api\Type\InlineKeyboardButton;
use Vjik\TelegramBot\Api\Type\InlineKeyboardMarkup;

class Service
{
    public function __construct(
        private Telegram $tg,
        private KeeneticApi $keenetic
    )
    {
    }

    public function handle(): void
    {
        $update = $this->tg->getUpdate();

        if ($update === null) {
            return;
        }

        $devices = $this->keenetic->getDevices();

        # Обработка текста собщения


        # Обработка нажатий кнопки
        if ($update->callbackQuery !== null) {
            $chaId = $update->callbackQuery->message->chat->id;

            $mac = $update->callbackQuery->data;

            $currentPolicy = $devices[$mac]['policy'] ?? 'default';

            $newPolicy = $currentPolicy === 'Policy0' ? 'default' : 'Policy0';

            $success = $this->keenetic->setPolicyDevice($mac, $newPolicy);

            $alert = $success
                ? "Политика для устройства {$mac} изменена на {$newPolicy}"
                : "Не удалось изменить политику";

            $this->tg->answerCallbackQuery(
                callbackQueryId: $update->callbackQuery->id,
                text: $alert,
                show_alert: true
            );

            return;
        }

        if ($update->message !== null) {
            $messageText = $update->message->text;
            $chatId = $update->message->chat->id;

            if ($messageText === '/start') {
                # Формируем кнопки
                $buttons = [];

                foreach ($devices as $mac => $device) {
                    $buttons[] = [
                        new InlineKeyboardButton(
                            text: "{$device['name']} ({$device['policy']})",
                            callbackData: "{$mac}"
                        )
                    ];
                }

                $this->tg->sendMessage(
                    chatId: $chatId,
                    text: '',
                    reply_markup: new InlineKeyboardMarkup($buttons)
                );
            } else {
                $this->tg->sendMessage(
                    chatId: $chatId,
                    text: 'Вызови /start'
                );
            }
        }
    }
}