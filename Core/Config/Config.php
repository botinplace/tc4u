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
            // Загружаем основной конфиг
            self::$config = self::loadConfigFile(CONFIG_DIR . 'config.php');
            
            // Применяем локальные переопределения
            self::applyLocalOverrides();
            
            // Вычисляемые значения
            self::$config['app']['fixed_uri'] = self::calculateFixedUri();

            self::validateConfig();
            self::$loaded = true;

        } catch (\Exception $e) {
            throw new \RuntimeException("Configuration loading failed: " . $e->getMessage());
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

    private static function applyLocalOverrides(): void
    {
        $localConfigPath = CONFIG_DIR . 'config.local.php';
        
        if (file_exists($localConfigPath)) {
            $localConfig = self::loadConfigFile($localConfigPath);
            self::$config = array_merge(self::$config, $localConfig);
        }
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

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
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
