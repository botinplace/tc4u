<?php
namespace Core;

use Core\Config\Config;
use Core\Request;
use Core\Router;

if (
    file_exists($_SERVER["SCRIPT_FILENAME"]) &&
    pathinfo($_SERVER["SCRIPT_FILENAME"], PATHINFO_EXTENSION) !== "php"
) {
    return;
}

class Application
{
    private $config;
    public $pageData = [
        "title" => "My Application",
        "description" => "This is a sample app.",
    ];
    public $routes;
    private $router;

    public function __construct()
    {
        $this->config = Config::loadConfig(APP . "Config/config.php");
        $this->router = new Router();
        $this->dispatchCurrentRoute();
    }

    private function dispatchCurrentRoute()
    {
        $path = $this->sanitizePath($_SERVER["REQUEST_URI"]);
        try {
            $this->router->dispatch($path);
        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    private function sanitizePath($uri)
    {
        $path = filter_var(trim($uri), FILTER_SANITIZE_URL);
        $path = explode("?", $path, 2)[0];

        // Удаление лишнего (например /admin/)
        if( URI_FIXER !== "" ){
            $path = preg_replace( "/^" . preg_quote(URI_FIXER, "/") . '(\/|$)/', "/", $path );
        }
                
        if( BASE_URL !== "" ){
            $path = preg_replace( "/^\/" . preg_quote(BASE_URL, "/") . '(\/|$)/', "/", $path )
        }

        return $path ?: "/";
    }

    private function handleError(\Exception $e)
    {
        http_response_code(500);
        echo "Ошибка: " .
            htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8");
    }

    public function run()
    {
    }
}
