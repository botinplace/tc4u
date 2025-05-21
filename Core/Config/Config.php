<?php
namespace Core\Config;

//use Core\Config\LoadEnv;

class Config {
    private static $config = [];

    public static function load(): void {
        
       //LoadEnv::load(__DIR__ . '/.env');

        // Основной файл
        self::$config = require CONFIG_DIR . 'config.php';

	// Загрузка DI-конфига
        self::$config['di'] = require CONFIG_DIR . 'di.php';

        if (!self::$config) {
            throw new \Exception("Config not loaded properly");
        }

        // Локальные переопределения
        //if (file_exists(CONFIG_DIR . 'config.local.php')) {
        //    self::$config = array_merge(self::$config, require CONFIG_DIR . 'config.local.php');
        //}

        // Конфиг окружения
        $env = env('APP_ENV', 'production');
        if (file_exists(CONFIG_DIR . "config.{$env}.php")) {
            $envConfig = require CONFIG_DIR . "config.{$env}.php";
            self::$config = array_replace_recursive(self::$config, $envConfig);
        }

        $fixed_uri = !empty( self::$config['app']['uri_fixer'] ) ? self::$config['app']['uri_fixer'] . (!empty(BASE_URL) ? '/' : '') : BASE_URL;
        self::$config['app']['fixed_uri'] = $fixed_uri;

        self::validateConfig();
    }

    public static function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) return $default;
            $value = $value[$k];
        }

        return $value;
    }

    private static function validateConfig(): void {
        $requiredKeys = ['app.uri_fixer'];
        foreach ($requiredKeys as $key) {
            if (self::get($key) === null) {
                throw new \Exception("Missing required config key '{$key}'");
            }
        }
    }
}
