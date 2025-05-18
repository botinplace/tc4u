<?php
declare(strict_types=1);

namespace Core\Config;

// PHP 8.1+ поддержка readonly свойств
class LoadEnv
{
    public static function load(string $env): void
    {
        // Сначала загружаем переменные окружения сервера
        self::loadServerEnv();

        // Затем загружаем из файла .env (если есть)
        if (file_exists($env)) {
            self::loadEnvFile($env);
        }
    }

    private static function loadServerEnv(): void
    {
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'DB_') === 0 || strpos($key, 'APP_') === 0) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    private static function loadEnvFile(string $env): void
    {
        $lines = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                throw new \InvalidArgumentException("Некоректное значение в строке: $line");
            }

            list($key, $value) = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value, "'\" \t\n\r\0\x0B");

            // Не перезаписываем переменные, уже установленные на сервере
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}
