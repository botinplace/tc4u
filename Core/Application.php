<?php
namespace Core;

use Core\Config\Config;
use Core\Request;
use Core\Router;

if (file_exists($_SERVER['SCRIPT_FILENAME']) && pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_EXTENSION) !== 'php') {
    return;
}

class Application {
    private $config;
	public  $pageData = ['title' => 'My Application', 'description' => 'This is a sample app.'];
	public $routes;
    private $router;    
    public function __construct() {
		// Загрузка конфигурации
        $this->config = Config::loadConfig(APP . 'Config/config.php');
		
		define('FIXED_URL', ( !empty(URI_FIXER) ? URI_FIXER.(!empty(BASE_URL) ? '/' : '') : '').BASE_URL );
		
		// Загрузка маршрутов из файла
        $this->routes = $this->loadFile(APP . 'routes.php');
		
        // Определяем версию PHP и загружаем соответствующий маршрут
        //$this->initializeRouter();
		
		$this->router = new Router();
		$this->dispatchCurrentRoute();
    }
	
	public function loadFile(string $filePath){
		return file_exists($filePath) ? require $filePath : ([]);
	}

    //private function initializeRouter() {        
		//Подключение роутера
		//$phpVersionClass = (PHP_VERSION_ID < 80000) ? '/Router7.php' : '/Router7.php';
		//require_once __DIR__ . $phpVersionClass;
		//$this->router = new Router();

        // Получение текущего пути и метода
        //$this->dispatchCurrentRoute();
    //}

    private function dispatchCurrentRoute() {
        // Получение и очистка текущего пути
        $path = filter_var(trim($_SERVER['REQUEST_URI']), FILTER_SANITIZE_URL);
        $path = explode('?', $path, 2)[0];

        // Удаление лишнего (например /admin/)
        $path = (URI_FIXER !== '') ? preg_replace('/^' . preg_quote(URI_FIXER, '/') . '(\/|$)/', '/', $path) : $path;
        $path = (BASE_URL !== '') ? preg_replace('/^\/' . preg_quote(BASE_URL, '/') . '(\/|$)/', '/', $path) : $path;
        $path = $path ?: '/';

        // Запуск маршрутизации
        try {
            $this->router->dispatch($path);
        } catch (Exception $e) {
			http_response_code(500);
            echo 'Ошибка: ' . $e->getMessage();			
        }
    }

    public function run() {
		
	}
}
