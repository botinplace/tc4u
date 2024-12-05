<?php
namespace Core;

class Request {
    // Получение данных из метода GET
    public function get($key, $default = null) {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    // Получение данных из метода POST
    public function post($key, $default = null) {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    // Получение всех данных из метода GET
    public function getAll() {
        return $_GET;
    }

    // Получение всех данных из метода POST
    public function postAll() {
        return $_POST;
    }

    // Получение заголовков запроса
    public function headers() {
        return getallheaders();
    }

    // Получение метода запроса (GET, POST, PUT и др.)
    public function method() {
        return $_SERVER['REQUEST_METHOD'];
    }

    // Проверка, является ли запрос AJAX
    public function isAjax() {
        return !empty($this->headers()['X-Requested-With']) && $this->headers()['X-Requested-With'] === 'XMLHttpRequest';
    }

    // Получение строки запроса
    public function fullUrl() {
        return (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}