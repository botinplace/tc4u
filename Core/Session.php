<?php
class Session {
    // Начинаем сессию, если она не была начата
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Устанавливаем значение сессии
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    // Получаем значение сессии
    public function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    // Удаляем значение сессии
    public function delete($key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    // Удаляем все значения сессии
    public function destroy() {
        session_unset();
        session_destroy();
    }
}