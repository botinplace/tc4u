<?php
namespace Core\Config;

class Config 
{
    private static array $config = [];
    private static bool $loaded = false;
    private static array $requiredKeys = ['app.uri_fixer'];

    public static function load(): void 
    {
        if (self::$loaded) {
            return;
        }

        try {
            // Загружаем базовый конфиг
            self::$config = self::loadConfigFile(CONFIG_DIR . 'config.php');
            
            // Загружаем конфиг окружения
            self::loadEnvironmentConfig();
            
            // Вычисляем дополнительные параметры
            self::$config['app']['fixed_uri'] = self::calculateFixedUri();

            self::validateConfig();
            self::$loaded = true;

        } catch (\Exception $e) {
            throw new \RuntimeException("Config loading failed: " . $e->getMessage());
        }
    }

    private static function loadEnvironmentConfig(): void
    {
        $env = env('APP_ENV', 'production');
        $envConfigPath = CONFIG_DIR . "config.{$env}.php";
        
        if (file_exists($envConfigPath)) {
            $envConfig = self::loadConfigFile($envConfigPath);
            self::$config = array_replace_recursive(self::$config, $envConfig);
        }
    }

    private static function loadConfigFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $config = require $path;
        
        if (!is_array($config)) {
            throw new \RuntimeException("Config file must return an array");
        }
        
        return $config;
    }

    private static function calculateFixedUri(): string
    {
        $uriFixer = self::$config['app']['uri_fixer'] ?? '';
        $baseUrl = BASE_URL ?? '';
        
        if (empty($uriFixer)) {
            return $baseUrl;
        }
        
        return rtrim($uriFixer, '/') . (!empty($baseUrl) ? '/' . ltrim($baseUrl, '/') : '');
    }

    public static function get(string $key, $default = null) 
    {
        if (!self::$loaded) {
            self::load();
        }

        $value = self::$config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private static function validateConfig(): void 
    {
        foreach (self::$requiredKeys as $key) {
            if (self::get($key) === null) {
                throw new \RuntimeException("Missing required config key: {$key}");
            }
        }
    }

    public static function reload(): void
    {
        self::$loaded = false;
        self::$config = [];
    }
    
    public static function setRequiredKeys(array $keys): void
    {
        self::$requiredKeys = $keys;
    }
}
