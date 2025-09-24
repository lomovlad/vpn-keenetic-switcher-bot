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
     * Возвращает список избранных устройств из полного массива устройств.
     *  Метод фильтрует переданный массив `$devices`, оставляя только устройства,
     *  MAC-адреса которых входят в заранее определённый список избранных
     * @return array
     * @throws GuzzleException
     */
    public function getFavDevices(): array
    {
        $result = [];
        $devices = $this->keenetic->getDevices();

        foreach ($this->storage->getFavMacs() as $mac) {
            if (isset($devices[$mac])) {
                $result[$mac] = $devices[$mac];
            }
        }

        return $result;
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

        if ($update->callbackQuery !== null) {
            $this->handleCallbackQuery($update->callbackQuery);

            return;
        }

        $this->handleMessage($update->message);
    }

    /**
     * Обработка callback-запроса от Telegram (нажатие на inline-кнопку).
     * @param object $callbackQuery
     * @return void
     * @throws GuzzleException
     */
    private function handleCallbackQuery(object $callbackQuery): void
    {
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->messageId;
        $mac = $callbackQuery->data;
        $favDevices = $this->getFavDevices();
        $currentPolicy = $favDevices[$mac]['policy'] ?? 'default';
        $newPolicy = $currentPolicy === 'Policy0' ? 'default' : 'Policy0';
        $success = $this->keenetic->setPolicyDevice($mac, $newPolicy);

        if (isset($favDevices[$mac])) {
            $favDevices[$mac]['policy'] = $newPolicy;
        }

        $buttons = $this->getDeviceButtons($favDevices);
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
     * @return void
     * @throws GuzzleException
     */
    private function handleMessage(object $message): void
    {
        $chatId = $message->chat->id;
        $text = $message->text ?? '';
        $favDevices = $this->getFavDevices();

        // Получаем ID последнего сообщения бота
        $lastMessageId = $this->storage->getLastMessageId($chatId);

        // Удаляем предыдущее сообщение, если есть
        if ($lastMessageId) {
            $this->tg->deleteMessage($chatId, $lastMessageId);
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
        $this->storage->setLastMessageId($chatId, $newMessageId);
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
