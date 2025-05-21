<?php
namespace Core;

use Core\Config\Config;
use Core\Request;
use Core\Router;

class Application
{
    // можно удалить
	private Container $container;
    	private Router $router;

    public function __construct()
    {
        $this->container = new Container();
        $this->registerCoreServices();
    }

    private function dispatchCurrentRoute()
    {
        $path = $this->container->get(Request::class);
	$path = $this->sanitizePath( $path::url() );	
		
        try {
			
		$this->router = $this->container->get(Router::class);
        
		$this->router->dispatch( $path );
			
		//$this->router->dispatch($path);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

	private function registerCoreServices(): void
	{

        	// Загрузка конфигурации DI
	        $diConfig = Config::get('di', []);
        
/*
	        foreach ($diConfig['bindings'] ?? [] as $abstract => $concrete) {
	            $this->container->bind($abstract, $concrete);
        	}
        
	  	foreach ($diConfig['singletons'] ?? [] as $abstract => $concrete) {
         		$this->container->singleton($abstract, $concrete);
	        }
*/

	        foreach ($diConfig['bindings'] ?? [] as $abstract => $concrete) {
            		if (is_callable($concrete)) {
                		$this->container->bind($abstract, $concrete);
           		} else {
                		$this->container->bind($abstract, $concrete);
	        	}
        	}

        	// Регистрация синглтонов
        	foreach ($diConfig['singletons'] ?? [] as $abstract => $concrete) {
            		if (is_array($concrete)) {
                		$this->container->singleton($abstract, ...$concrete);
            		} else {
                		$this->container->singleton($abstract, $concrete);
            		}
        	}


		// Регистрация основных компонентов
		$this->container->bind(Response::class);
		
		$this->container->singleton(Request::class, function($c) {
			return new Request();
		});
		
		$this->container->singleton(Session::class, function($c) {
			return new Session();
		});

		$this->container->bind(Router::class, function($c) {
			return new Router(
				$c, // Container
				$c->get(Response::class) // Response
			);
		});
		
	}

    private function sanitizePath($uri)
    {
        $path = filter_var(trim($uri), FILTER_SANITIZE_URL);
        $path = explode("?", $path, 2)[0];

        // Удаление лишнего (например /admin/)
        $uri_fixer = Config::get('app.uri_fixer');
        if ($uri_fixer && $uri_fixer !== "") {
            $path = preg_replace("/^" . preg_quote( $uri_fixer , "/") . '(\/|$)/', "/", $path);
        }

        if (defined('BASE_URL') && BASE_URL !== "") {
            $path = preg_replace("/^\/" . preg_quote(BASE_URL, "/") . '(\/|$)/', "/", $path);
        }

        return $path ?: "/";
    }

    private function handleError(\Exception $e)
    {
    $errorMessage = sprintf(
        "[%s] Ошибка 500: %s в файле %s на строке %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    
    error_log($errorMessage);
        //http_response_code(500);        
        //echo "Ошибка: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8");
    }

    public function run()
    {        
        $this->dispatchCurrentRoute();
    }
}
