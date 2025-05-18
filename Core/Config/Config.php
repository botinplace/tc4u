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
            // Загружаем конфиги с приоритетом: local > env > base
            self::$config = array_merge(
                self::loadConfigFile(CONFIG_DIR . 'config.php'),
                self::loadEnvironmentConfig(),
                self::loadLocalConfig()
            );

            // Вычисляемые значения
            self::$config['app']['fixed_uri'] = self::calculateFixedUri();

            self::validateConfig();
            self::$loaded = true;

        } catch (\Throwable $e) {
            throw new \RuntimeException("Config loading failed: " . $e->getMessage());
        }
    }

    private static function loadEnvironmentConfig(): array
    {
        $env = env('APP_ENV', 'production');
        $envConfigPath = CONFIG_DIR . "config.{$env}.php";
        
        return file_exists($envConfigPath) ? self::loadConfigFile($envConfigPath) : [];
    }

    private static function loadLocalConfig(): array
    {
        $localConfigPath = CONFIG_DIR . 'config.local.php';
        return file_exists($localConfigPath) ? self::loadConfigFile($localConfigPath) : [];
    }

    private static function loadConfigFile(string $path): array
    {
        $config = @include $path;
        
        if (!is_array($config)) {
            throw new \InvalidArgumentException("Config file {$path} must return an array");
        }
        
        return $config;
    }

    private static function calculateFixedUri(): string
    {
        $uriFixer = self::$config['app']['uri_fixer'] ?? '';
        $baseUrl = BASE_URL ?? '';
        
        if (!empty($uriFixer) {
            return rtrim($uriFixer, '/') . '/' . ltrim($baseUrl, '/');
        }
        
        return $baseUrl;
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

    public static function all(): array
    {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config;
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
