<?php
namespace Core;

class Request
{
    private static ?array $parsedBody = null;

    // Получение данных с фильтрацией
    public static function get(string $key, mixed $default = null, int $filter = FILTER_DEFAULT, array|int|null $options = null): mixed
    {
        return filter_input(INPUT_GET, $key, $filter, $options ?? 0) ?? $default;
    }

    public static function post(string $key, mixed $default = null, int $filter = FILTER_DEFAULT, array|int|null $options = null): mixed
    {
        return filter_input(INPUT_POST, $key, $filter, $options ?? 0) ?? $default;
    }

    // Получение всех данных с фильтрацией
    public static function getAll(int $filter = FILTER_DEFAULT, array|int|null $options = null): array
    {
        return filter_input_array(INPUT_GET, [
            '*' => ['filter' => $filter, 'options' => $options ?? []]
        ]) ?? [];
    }

    public static function postAll(int $filter = FILTER_DEFAULT, array|int|null $options = null): array
    {
        return filter_input_array(INPUT_POST, [
            '*' => ['filter' => $filter, 'options' => $options ?? []]
        ]) ?? [];
    }

    // Получение данных из любого источника (GET/POST)
    public static function input(string $key, mixed $default = null, int $filter = FILTER_DEFAULT, array|int|null $options = null): mixed
    {
        return self::post($key, self::get($key, $default, $filter, $options), $filter, $options);
    }

    // Улучшенная обработка JSON
    public static function json(?string $key = null, mixed $default = null): mixed
    {
        if (self::$parsedBody === null) {
            $input = file_get_contents('php://input');
            self::$parsedBody = json_decode($input, true) ?? [];
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }
        }

        if ($key === null) {
            return self::$parsedBody;
        }

        return self::$parsedBody[$key] ?? $default;
    }

    // Получение файлов с обработкой
    public static function files(?string $key = null): mixed
    {
        if ($key === null) {
            return $_FILES;
        }
        
        return $_FILES[$key] ?? null;
    }

    // Работа с заголовками
    public static function headers(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }

    public static function header(string $key, $default = null)
    {
        $key = strtoupper(str_replace('-', '_', $key));
        return $_SERVER['HTTP_' . $key] ?? $_SERVER[$key] ?? $default;
    }

    // Получение метода с поддержкой методов override
    public static function method(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            return strtoupper(self::input('_method', $method));
        }

        return $method;
    }

    // Проверка типа запроса
    public static function isAjax(): bool
    {
        return strtolower(self::header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public static function isJson(): bool
    {
        return strpos(self::header('Content-Type', ''), 'application/json') !== false;
    }

    // Работа с URL
    public static function url(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public static function fullUrl(): string
    {
        return (self::isSecure() ? 'https://' : 'http://') . 
               ($_SERVER['HTTP_HOST'] ?? 'localhost') . 
               ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||($_SERVER['SERVER_PORT'] ?? 80) == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https');
    }

    // Работа с IP-адресом
    public static function ip(): string
    {
        return $_SERVER['HTTP_CLIENT_IP'] ?? 
               $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
               $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    // Проверка методов
    public static function isMethod(string $method): bool
    {
        return strtoupper($method) === self::method();
    }

    // Bearer Token
    public static function bearerToken(): ?string
    {
        $header = self::header('Authorization', '');
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }
}
