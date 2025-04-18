<?php
namespace Core;

class Session {
    private static bool $isStarted = false;

    // Старт сессии с улучшенными настройками безопасности
    public static function start(array $options = []): void {
        if (self::$isStarted) {
            return;
        }

        $defaultOptions = [
            'name' => 'secure_sid',
            'cookie_lifetime' => 86400, // 1 день
            //'cookie_path' => '/',
            //'cookie_domain' => $_SERVER['HTTP_HOST'] ?? '',
            'cookie_secure' => isset($_SERVER['HTTPS']),
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'use_only_cookies' => 1,
            'cache_limiter' => 'nocache'
            //Устаревшие параметры (с 8.1 ):
            //'sid_length' => 128,
            //'sid_bits_per_character' => 6
        ];

        session_start(array_merge($defaultOptions, $options));
        self::$isStarted = true;
    }

    // Регенерация ID с защитой от fixation-атак
    public static function regenerate(): bool {
        self::ensureStarted();
        return session_regenerate_id(true);
    }

    // Установка значения с поддержкой dot-notation
    public static function set(string $key, $value): void {
        self::ensureStarted();
        
        $keys = explode('.', $key);
        $session = &$_SESSION;
        
        foreach ($keys as $k) {
            $session = &$session[$k];
        }
        
        $session = $value;
    }

    // Получение значения с dot-notation и строгой типизацией
    public static function get(string $key, $default = null) {
        self::ensureStarted();
        
        $keys = explode('.', $key);
        $session = $_SESSION;
        
        foreach ($keys as $k) {
            if (!isset($session[$k])) {
                return $default;
            }
            $session = $session[$k];
        }
        
        return $session;
    }

    // Проверка наличия ключа
    public static function has(string $key): bool {
        self::ensureStarted();
        
        $keys = explode('.', $key);
        $session = $_SESSION;
        
        foreach ($keys as $k) {
            if (!isset($session[$k])) {
                return false;
            }
            $session = $session[$k];
        }
        
        return true;
    }

    // Удаление значения
    public static function remove(string $key): void {
        self::ensureStarted();
        
        $keys = explode('.', $key);
        $session = &$_SESSION;
        
        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                unset($session[$k]);
                break;
            }
            
            if (!isset($session[$k])) {
                break;
            }
            
            $session = &$session[$k];
        }
    }

    // Полная очистка данных сессии
    public static function clear(): void {
        self::ensureStarted();
        $_SESSION = [];
    }

    // Уничтожение сессии с очисткой куки
    public static function destroy(): void {
        if (!self::$isStarted) {
            return;
        }
        
        self::clear();
        
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
        
        session_destroy();
        self::$isStarted = false;
    }

    // Flash-сообщения (одноразовые данные)
    public static function flash(string $key, $value): void {
        self::set("_flash.{$key}", $value);
    }

    // Получение flash-сообщения
    public static function getFlash(string $key, $default = null) {
        $value = self::get("_flash.{$key}", $default);
        self::remove("_flash.{$key}");
      return $value;
    }

    private static function ensureStarted(): void {
        if (!self::$isStarted) {
            //throw new \RuntimeException('Session not started');
            self::start();
        }
    }
}
