# Switcher VPN Keenetic Bot

**Telegram-бот для управления политиками подключения устройств на роутере Keenetic через VPN.**

## 📝 Описание

Бот позволяет переключать политику подключения отдельных устройств в реальном времени. Использует Telegram API для взаимодействия с пользователем и Keenetic RCI API для управления роутером.

**Функционал:**

- Просмотр списка устройств, подключённых к роутеру
- Переключение политики подключения устройства (например, `Policy0` / `default`) через inline-клавиатуру Telegram
- Синхронизация локальной базы данных с состоянием устройств на роутере
- Поддержка работы через VPN

## 🛠 Технологии

- **PHP 8+**
- [GuzzleHTTP](https://github.com/guzzle/guzzle) — для HTTP-запросов к роутеру
- [Longman Telegram Bot](https://github.com/php-telegram-bot/core) — библиотека для работы с Telegram API
- [Dotenv](https://github.com/vlucas/phpdotenv) — для работы с переменными окружения
- **MySQL** — хранение информации об устройствах

## ⚡ Установка

1. Клонируйте репозиторий:

   ```git clone https://github.com/ваш_пользователь/switcher_vpn_keenetic_bot.git```
2. Перейдите в директорию проекта:

    ```cd switcher_vpn_keenetic_bot```
3. Установите зависимости через Composer:
   ```composer install```
4. Создайте файл `.env` и добавьте конфигурацию:
   
```
ROUTE_BASEURI=адрес_роутера
ROUTE_LOGIN=логин
ROUTE_PASS=пароль
DB_HOST=localhost
DB_NAME=имя_бд
DB_USER=пользователь
DB_PASS=пароль
DB_CHARSET=utf8mb4
TOKEN_TELEGRAM=токен_бота
USERNAMEBOT_TELEGRAM=имя_бота
```

5. Запустите бота:
   ```php index.php```

## 🚀 Использование

1. Отправьте `/start` в Telegram-боте
2. Бот покажет список устройств с текущей политикой подключения
3. Нажмите на кнопку устройства, чтобы переключить политику.
