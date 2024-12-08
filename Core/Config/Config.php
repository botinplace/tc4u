<?php
namespace Core\Config;

static class Config{    
	public static function loadConfig($configFilePath) {
        if (file_exists($configFilePath)) {
            require $configFilePath;
        } else {            
            $self->defineDefaultConstants();
            //error_log("Создайте файл конфигурации: " . $configFilePath . ' на базе шаблона config.simple.php ');
			//throw new \Exception('Create config.php based on config.sample.php');
			die('Create config.php based on config.sample.php');
        }
    }
	
	private function defineDefaultConstants() {
			if (!defined('URI_FIXER')) {
				define('URI_FIXER', '');
			}
		}
}