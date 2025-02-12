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

    public function __construct()
    {
        $this->routesFile = APP . "routes.php";
        $this->cacheFile = APP . "cache/cachroutes.php";
        $this->cache = new Cache($this->cacheFile, $this->routesFile);
        $this->response = new Response();
        $this->loadRoutesFromCache(); // Загрузка маршрутов из кэша
        $this->loadRoutesFromFile(); // В случае, если кэш не существует
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
        $routes = file_exists($this->routesFile)
            ? require $this->routesFile
            : [];
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
                 $this->routes[] = $route;
             }            
        }
        $this->cache->save($this->routes);
    }

    public function dispatch(string $path): void
    {
        $normalizedPath = $this->normalizePath($path);
        $method = strtoupper( Request::method() );

        foreach ($this->routes as $route) {
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
            if ( Request::isAjax() ) {
                // Возврат JSON ответа для AJAX-запроса
                $this->response->setJson( ['error' => 'Unauthorized', 'redirect' => '/auth'] );
            } else {
                // Перенаправление для обычного запроса
                $this->response->setHeader('location','/auth');
            }
            $this->send();
        }

        [$class, $function] = $this->resolveController($route);

        if (!class_exists($class) || !method_exists($class, $function)) {
            [$class, $function] = ["Core\Controller", "renderEmptyPage"];
        }

        $basename = ucfirst(basename($route->path));
        $this->pageData["basetemplate"] = isset($route->basetemplate)
            ? $route->basetemplate
            : "base";
        $this->pageData["contentFile"] = isset($route->contentfile)
            ? $route->contentfile
            : (!empty($basename)
                ? $basename
                : "Index");
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
        return "/" . trim(preg_replace("#[/]{2,}#", "/", $path), "/");
    }

    private function isUserAuthenticated(): bool
    {
        return isset($_SESSION["user_id"]);
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
