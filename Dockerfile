FROM php:8.4-fpm

# Ставим Xdebug
RUN pecl install xdebug-3.4.2 \
    && docker-php-ext-enable xdebug

# Рабочая директория
WORKDIR /var/www
