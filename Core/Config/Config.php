<?php
namespace Core\Config;

class Config{    
	public static function loadConfig($configFilePath) {
        if (file_exists($configFilePath)) {
            require $configFilePath;
        } else {            
            self::defineDefaultConstants();
			//echo '<div style="background:red;color:white;padding:20px;position:relative;top:0;left:0;right:0;z-index:999999">Создайте файл "config.php" в папке Config на основе файла config.sample.php</div>';
		if (!defined('CORE_INFO_MESSAGE')) {
			define('CORE_INFO_MESSAGE','<div style="background:red;color:white;padding:20px;position:relative;top:0;left:0;right:0;z-index:999999">Создайте файл "config.php" в папке Config на основе файла config.sample.php</div>');	
		}
		
        }
		define('FIXED_URL', ( !empty(URI_FIXER) ? URI_FIXER.(!empty(BASE_URL) ? '/' : '') : '').BASE_URL );
		
		if (!defined('ALLOWED_METHODS')) {
			define('ALLOWED_METHODS',['GET','POST','PUT','DELETE','OPTIONS','HEAD'] );
		}
		
    }
	
	private static function defineDefaultConstants() {
			if (!defined('URI_FIXER')) {
				$scriptName = $_SERVER['SCRIPT_NAME'];
				define('URI_FIXER', dirname(dirname($scriptName)));
			}
		}
}
