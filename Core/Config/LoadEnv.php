<?php
declare(strict_types=1);

namespace Core\Config;

// PHP 8.1+ поддержка readonly свойств
class LoadEnv
{

    public static function load(string $env): void
    {
        if (!file_exists($env)) {
            throw new \InvalidArgumentException("файл $env не найден");
        }

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

            if (array_key_exists($key, $_ENV)) {
                throw new \RuntimeException("Дублирующиеся: $key");
            }


            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}
