<?php
namespace Core\Config;

class Config {
    private static $config = [];

    public static function load(): void {
        // Основной файл
        self::$config = require APP . 'config.php';
		if (!self::$config) {
			throw new \Exception("Config not loaded properly");
		}

        // Локальные переопределения
        if (file_exists(APP . 'config.local.php')) {
            self::$config = array_merge(self::$config, require APP . 'config.local.php');
        }

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
