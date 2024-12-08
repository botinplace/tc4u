<?php
namespace Core\Config;

class Config{    
	public static function loadConfig($configFilePath) {
        if (file_exists($configFilePath)) {
            require $configFilePath;
        } else {            
            self::defineDefaultConstants();
			die('Создайте файл "config.php" в папке Config на основе файла config.sample.php');
        }
    }
	
	private static function defineDefaultConstants() {
			if (!defined('URI_FIXER')) {
				define('URI_FIXER', '');
			}
		}
}