<?php
namespace Core;

class Session {
    // Старт сессии
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Установка значения
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }

    // Получение значения
    public static function get($key) {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    // Удаление значения
    public static function remove($key) {
        self::start();
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    // Уничтожение сессии
    public static function destroy() {
        self::start();
        session_unset();
        session_destroy();
    }

    // Закрытие записи сессии
    public static function close() {
        if (session_status() !== PHP_SESSION_NONE) {
            session_write_close();
        }
    }
}
