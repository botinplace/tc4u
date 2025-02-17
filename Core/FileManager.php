<?php
namespace Core;

use RuntimeException;

class FileManager {
    // Возвращает(подключает) файл
    public static function requireFile(string $filePath, $default = []): mixed
    {
        // Проверка на существование файла
        if (!file_exists($filePath)) {
            throw new RuntimeException("Файл не найден: {$filePath}");
        }

        // Подключение файла и обработка ошибок
        try {
            $result = require $filePath;
        } catch (Exception $e) {
            throw new RuntimeException("Ошибка при подключении к файлу: {$filePath}. Ошибка: " . $e->getMessage());
        }

        // Проверка на ошибки при подключении
        if ($result === false) {
            throw new RuntimeException("Ошибка при подключении к файлу: {$filePath}");
        }

        
        // Проверка на ожидаемый формат данных (можно изменить в зависимости от ожиданий)
        if( gettype($result)!= gettype($default) ){
            throw new RuntimeException("Файл должен возвращать массив или null: {$filePath}");
        }

        return $result ?? $default;
    }
    // Возвращает содержимое файла
    public static function read(string $filePath,$default = null): string
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Файл не найден: {$filePath}");
        }
        return file_get_contents($filePath);
    }

    public static function save(string $filePath, string $data): void
    {
        $dirPath = dirname($filePath);
        if (!is_dir($dirPath)) {
            if (!mkdir($dirPath, 0755, true)) {
                throw new RuntimeException("Не удалось создать директорию: {$dirPath}");
            }
        }

        if (file_put_contents($filePath, $data) === false) {
            throw new RuntimeException("Не удалось записать данные в файл: {$filePath}");
        }
    }

    public static function delete(string $filePath): void
    {
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                throw new RuntimeException("Не удалось удалить файл: {$filePath}");
            }
        }
    }

    public static function fileExists(string $filePath): bool
    {
        return file_exists($filePath);
    }
}
