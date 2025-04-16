<?php
namespace Core;

use Core\Cache;
use Core\Response;

class Router
{
    private $routes = [];
    private $cache;
    private $routesFile;
    private $cacheFile;
    private $pageData = [];
    private $response;
    private $allowedMethods = ['GET,POST,PUT,DELETE,OPTIONS,HEAD'];

    public function __construct()
    {
        $this->routesFile = APP . "Config/routes.php";
        $this->cacheFile = APP . "cache/cachroutes.php";
        $this->cache = new Cache($this->cacheFile, $this->routesFile);
        $this->response = new Response();
        $this->setAllowedMethods();
        $this->loadRoutesFromCache(); // Загрузка маршрутов из кэша
        $this->loadRoutesFromFile(); // В случае, если кэш не существует
    }

    private function setAllowedMethods()
    {
        if (!empty(ALLOWED_METHODS)) {
            $this->allowedMethods = array_map('trim', explode(',', ALLOWED_METHODS));
        }
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
                $route = new Route(                    
                    $method,
                    $routeData["path"],
                    $data["controller"],
                    $data["needauth"] ?? false,
                    // добавить $data["onlyforguest"] ?? false,
                    $key
                );
                  //$this->routes[$method][$routeData["path"]] = $route;
                 $this->routes[$method][] = $route;
             }            
        }
    
        $this->cache->save($this->routes);
    
    }

    public function dispatch(string $path): void
    {
        $method = strtoupper( Request::method() );
        
         if (!in_array($method, $this->allowedMethods)) {                
                $this->response->setHeader('HTTP/1.1 405 Method Not Allowed');
                $this->send();
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

    private function handleRoute(Route $route, array $params): void
    {
        // Если роут требует авторизации и пользователь не авторизован
        if ($route->needAuth && !$this->isUserAuthenticated()) {
	    error_log("Попытка несанкционированного доступа к маршруту: " . $route->path);

            if ( Request::isAjax() ) {
                // Возврат JSON ответа для AJAX-запроса
                $this->response->setJson( ['error' => 'Unauthorized', 'redirect' => AUTH_PATH ] );
            } else {
                // Перенаправление для обычного запроса
                $this->response->setHeader('location', AUTH_PATH );
            }
            $this->send();
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

        $controllerInstance = new $class($this->pageData); // Сюда pagedata!!!
        $controllerInstance->{$function}(...array_values($params));

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
	$user = Session::get('user');
        return isset( $user );
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
