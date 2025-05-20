<?php
namespace Core;

class Cookie {
    public static function set($name, $value, $expires = 0, $path = '/', $domain = '', $secure = false, $httponly = false) {
        setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
    }

    public static function get($name) {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

    public static function delete($name) {
        self::set($name, '', time() - 3600);
    }
}
