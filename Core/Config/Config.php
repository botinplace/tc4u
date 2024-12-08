<?php
namespace Core\Config;

class Config{    
	public static function loadConfig($configFilePath) {
        if (file_exists($configFilePath)) {
            require $configFilePath;
        } else {            
            self::defineDefaultConstants();
			echo '<div style="background:red;color:white;padding:20px;position:absolute;top:0;left:0;right:0;">Создайте файл "config.php" в папке Config на основе файла config.sample.php</div>';
        }
		define('FIXED_URL', ( !empty(URI_FIXER) ? URI_FIXER.(!empty(BASE_URL) ? '/' : '') : '').BASE_URL );
    }
	
	private static function defineDefaultConstants() {
			if (!defined('URI_FIXER')) {
				$scriptName = $_SERVER['SCRIPT_NAME'];
				define('URI_FIXER', dirname(dirname($scriptName)));
			}
		}
}