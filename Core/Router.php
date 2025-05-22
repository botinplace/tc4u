<?php
namespace Core;

use Core\Config\Config;
use Core\Cache;
use Core\Response;

class Router
{
    private $routes = [];
    private $cache;
    private $routesFile;
    private $cacheFile;
    private array $pageData = [];
    private $allowedMethods = ['GET,POST,PUT,DELETE,OPTIONS,HEAD'];
    private $globalMiddlewares = [];

    public function __construct(
		private Container $container,
		private Response $response
	)
    {
        $this->routesFile = CONFIG_DIR . "/routes.php";
        $this->cacheFile = APP_DIR . "cache/cachroutes.php";
        //$this->cache = new Cache($this->cacheFile, $this->routesFile);

		$this->cache = $container->get(Cache::class);
		
        $this->setAllowedMethods();
        $this->loadRoutesFromCache(); // Загрузка маршрутов из кэша
        $this->loadRoutesFromFile(); // В случае, если кэш не существует
		$this->globalMiddlewares = Config::get('middleware.global');
    }

    private function setAllowedMethods()
    {
           $this->allowedMethods = array_map('trim', Config::get('app.allowed_methods', $this->allowedMethods ) );
    }
    private function loadRoutesFromCache()
    {
        $routes = $this->cache->load();
        if (!empty($routes)) {
            $this->routes = $routes;
        }
    }

    private function loadRoutesFromFile()
    {
        $routes = [];
            
        if(!file_exists($this->routesFile)){
           trigger_error("Файл $filePath отсутствует!.");       
        }

        try {
            $routes = include $this->routesFile;

        if (!is_array($routes)) {
			trigger_error("Данные в файле $filePath должны быть массивом.");
            $routes=[];
        }
        
        
    } catch (\Throwable $e) {
		trigger_error("Ошибка при загрузке файла $this->routesFile: " . $e->getMessage(), E_USER_WARNING);
        $routes = [];
    }
        $this->setRoutes($routes);
    }

    private function setRoutes(array $routes): void
    {
        if (!empty($this->routes)) {
            return;
        }

        foreach ($routes as $key=>$routeData) {
             foreach ($routeData['methods'] as $method => $data) {
		$methods = explode('|', $method);
		foreach ($methods as $singleMethod) {
                $route = new Route(                    
                    $singleMethod,
                    $routeData["path"],
                    $data["controller"],
                    $data["needauth"] ?? false,
                    // добавить $data["onlyforguest"] ?? false,
                    $key,
					$data["middlewares"] ?? [],
                );
                  //$this->routes[$method][$routeData["path"]] = $route;
                 $this->routes[ $singleMethod ][] = $route;
		}
             }            
        }
        $this->cache->save($this->routes);
    
    }

    public function dispatch(string $path): void
    {
        $method = strtoupper( Request::method() );
        
         if (!in_array($method, $this->allowedMethods)) {                
            //$this->response->setHeader('HTTP/1.1 405 Method Not Allowed');
			$this->response->setStatusCode(405);
            $this->response->send();
            return;
		}

        $normalizedPath = $this->normalizePath($path);

        $routes = ( isset( $this->routes[$method] ) & is_array($this->routes[$method]) ) ? $this->routes[$method] : [];
        foreach ( $routes as $route) {
            if ($route->method !== $method) {
                continue;
            }

            $pattern = preg_replace(
                "/\{(\w+)\}/",
                '(?P<$1>[^/]+)',
                $route->path
            );
            if (
                !preg_match(
                    "#^{$pattern}$#",
                    urldecode($normalizedPath),
                    $matches
                )
            ) {
                continue;
            }

            array_shift($matches); // Удаляем первый элемент, он соответствует полному пути
            $params = array_filter($matches);
            $this->handleRoute($route, $params);
            return;
        }

        $this->handleNotFound(); // Обработка 404
    }

  private function processMiddlewares(array $middlewares, array $params, array &$pagedata): bool
    {
	$middlewares = array_merge($this->globalMiddlewares,$middlewares);
        foreach ($middlewares as $middleware) {
            if (!class_exists($middleware)) {
                error_log("Middleware class {$middleware} не найден");
                continue;
            }
            
			// OLD
            //$middlewareInstance = new $middleware();
            
			// DI
			$middlewareInstance = $this->container->make($middleware);			
			
            if (!method_exists($middlewareInstance, 'handle')) {
                error_log("Middleware {$middleware} handle метод не найден");
                continue;
            }
            
            $result = $middlewareInstance->handle($params , $pagedata);
            
            if ($result === false) {            
                return false;
            }
            
        }
        
        return true;
    }
    private function handleRoute(Route $route, array $params): void
    {
        // Если роут требует авторизации и пользователь не авторизован
        if ($route->needAuth && !$this->isUserAuthenticated()) {
	    error_log("Попытка несанкционированного доступа к маршруту: " . $route->path);
            $this->response->redirect( Config::get('app.auth.path') );
        }

        [$class, $function] = $this->resolveController($route);

        if (!class_exists($class) || !method_exists($class, $function)) {
            [$class, $function] = ["Core\Controller", "renderEmptyPage"];
        }

        $basename = ucfirst(basename($route->path));

        $this->pageData["routename"] = isset($route->name)
            ? $route->name
            : '';
        
        $this->pageData["needauth"] = isset($route->needAuth)
            ? $route->needAuth
            : false;

	if (!$this->processMiddlewares($route->middlewares, $params,$this->pageData)) {
            return;
        }
		
		// OLD
        //$controllerInstance = new $class($this->pageData);
        //$controllerInstance->{$function}(...array_values($params));
		
		// DI
		$controller = $this->container->make( $class ,  ['pagedata' => $this->pageData] );
		$controller->{$function}(...array_values($params));

        return;
    }

    private function handleNotFound(): void
    {
        $route = new Route("GET", "404", [ "Core\Controller","handleNotFound"]);
        $this->handleRoute($route, []);
    }

    private function resolveController(Route $route): array
    {
        return $route->controller ?: ["", ""];
    }

    private function normalizePath(string $path): string
    {
         //$normalized = "/" . trim(preg_replace("#[/]{2,}#", "/", $path), "/");
         //return $normalized === '' ? '/' : $normalized;
        return "/" . trim(preg_replace("#[/]{2,}#", "/", $path), "/");
    }

    private function isUserAuthenticated(): bool
    {
	return Session::get('user') !== null;
    }

    private function toArray(): array
    {
        return array_map(function ($route) {
            return [                
                "method" => $route->method,
                "path" => $route->path,
                "controller" => $route->controller,                
                "needauth" => $route->needAuth,                
            ];
        }, $this->routes);
    }
}
