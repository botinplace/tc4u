<?php
namespace Core;

class RequestOLD {
    // Получение данных из метода GET
    public static function get($key, $default = null) {
        return isset($_GET[$key]) ? $_GET[$key] : $default;
    }

    // Получение данных из метода POST
    public static function post($key, $default = null) {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    // Получение всех данных из метода GET
    public static function getAll() {
        return $_GET;
    }

    // Получение всех данных из метода POST
    public static function postAll() {
        return $_POST;
    }

    // Получение JSON данных из тела запроса
    public static function json() {
        $data = json_decode(file_get_contents('php://input'), true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }
	
    // Получение заголовков запроса
    public static function headers() {
        return getallheaders();
    }

    public static function header($key, $default = null) {
        $headers = self::headers();
        return isset($headers[$key]) ? $headers[$key] : $default;
    }

    // Получение метода запроса (GET, POST, PUT и др.)
    public static function method() {
        return $_SERVER['REQUEST_METHOD'];
    }

    // Проверка, является ли запрос AJAX
	/*
    public function isAjax() {
        return !empty($this->headers()['X-Requested-With']) && $this->headers()['X-Requested-With'] === 'XMLHttpRequest';
    }
	*/
	    public static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               in_array(strtolower($_SERVER['HTTP_X_REQUESTED_WITH']), ['xmlhttprequest', 'fetch']);
    }

    // Получение строки запроса
    public static function fullUrl() {
        return (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
}
