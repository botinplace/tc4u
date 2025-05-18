<?php
namespace Core\Config;

class Config 
{
    private static $config = [];
    private static $loaded = false;

    public static function load(): void 
    {
        if (self::$loaded) {
            return;
        }

        // Загружаем базовый конфиг
        $baseConfig = self::loadConfigFile(CONFIG_DIR . 'config.php');
        
        // Загружаем конфиг для окружения (если есть)
        $env = env('APP_ENV', 'production');
        $envConfigPath = CONFIG_DIR . "config.{$env}.php";
        
        $envConfig = file_exists($envConfigPath) 
            ? self::loadConfigFile($envConfigPath)
            : [];

        // Загружаем локальные переопределения (если есть)
        $localConfig = file_exists(CONFIG_DIR . 'config.local.php')
            ? self::loadConfigFile(CONFIG_DIR . 'config.local.php')
            : [];

        // Мерджим конфиги с приоритетом: local > env > base
        self::$config = array_merge($baseConfig, $envConfig, $localConfig);

        // Дополнительные вычисляемые значения
        self::$config['app']['fixed_uri'] = self::calculateFixedUri();

        self::validateConfig();
        self::$loaded = true;
    }

    private static function loadConfigFile(string $path): array
    {
        $config = require $path;
        
        if (!is_array($config)) {
            throw new \Exception("Config file {$path} must return an array");
        }
        
        return $config;
    }

    private static function calculateFixedUri(): string
    {
        $uriFixer = self::$config['app']['uri_fixer'] ?? '';
        $baseUrl = BASE_URL ?? '';
        
        return $uriFixer . (!empty($baseUrl) ? '/' . ltrim($baseUrl, '/') : '');
    }

    public static function get(string $key, $default = null) 
    {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    private static function validateConfig(): void 
    {
        $requiredKeys = ['app.uri_fixer'];
        
        foreach ($requiredKeys as $key) {
            if (self::get($key) === null) {
                throw new \Exception("Missing required config key '{$key}'");
            }
        }
    }
}
