<?php
namespace Core;

use RuntimeException;

class FileManager {
    public static function read(string $filePath,$default = null): string
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Файл не найден: {$filePath}");
        }
        // Ещё проверить на ошибки самого файла (если например php,json и т.д)
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
