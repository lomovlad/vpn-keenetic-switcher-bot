<?php

declare(strict_types=1);

namespace App;

use Vjik\TelegramBot\Api\ParseResult\TelegramParseResultException;
use Vjik\TelegramBot\Api\TelegramBotApi;
use Vjik\TelegramBot\Api\Type\Update\Update;

class Telegram
{
    private TelegramBotApi $botApi;

    /**
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->botApi = new TelegramBotApi($token);
    }

    /**
     * @return Update|null
     */
    public function getUpdate(): ?Update
    {
        $input = file_get_contents('php://input');
        try {

            return Update::fromJson($input);
        } catch (TelegramParseResultException $e) {

            return null;
        }
    }

    public function getBotApi(): TelegramBotApi
    {
        return $this->botApi;
    }
}