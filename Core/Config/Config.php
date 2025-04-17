<?php
namespace Core\Config;

class Config {
    private static $config = [];

    public static function load(): void {
        // Основной файл
        self::$config = require CONFIG_DIR . 'config.php';
		if (!self::$config) {
			throw new \Exception("Config not loaded properly");
		}

        // Локальные переопределения
        if (file_exists(CONFIG_DIR . 'config.local.php')) {
            self::$config = array_merge(self::$config, require CONFIG_DIR . 'config.local.php');
        }

	    $fixed_url = !empty( self::get('uri_fixer') ) ? self::get('uri_fixer').(!empty(BASE_URL) ? '/' : '') : '').BASE_URL;
	    [ 'app' => [ 'fixed_url' => '' ] ];

	app ('FIXED_URL', (  !empty(URI_FIXER) ? URI_FIXER.(!empty(BASE_URL) ? '/' : '') : '').BASE_URL );
	    
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
