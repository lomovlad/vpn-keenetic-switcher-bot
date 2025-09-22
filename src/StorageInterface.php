<?php

declare(strict_types=1);

namespace App;

interface StorageInterface
{
    public function loadStorage(string $file = 'storage.json'): array;
    public function saveStorage(array $data, string $file = 'storage.json'): void;
    public function updateStorage(int $chatId, array $newData, string $file = 'storage.json'): void;
}