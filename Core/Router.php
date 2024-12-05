<?php
namespace Core;

class Router {
    private array $routes = [];

    public function add(string $method, string $path, array $controller): void { 
        $path = $this->normalizePath($path);
        $this->routes[] = [
            'path' => $path,
            'method' => strtoupper($method),
            'controller' => $controller,
            'middlewares' => []
        ];
    }

    private function normalizePath(string $path): string { 
        $path = trim($path, '/'); 
        $path = "/{$path}/"; 
        $path = preg_replace('#[/]{2,}#', '/', $path);
        return $path;
        return preg_replace('#^/|/$#', '', $path) ? '/' . trim($path, '/') . '/' : '/';
    }

    public function dispatch(string $path): void {
        $path = $this->normalizePath($path); 
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Используем регулярное выражение для динамического сопоставления
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['path']);
            
            if (!preg_match("#^{$pattern}$#", $path, $matches)) {
                continue;
            }
            
            // Извлечение параметров
            array_shift($matches); // Убираем полное URL-совпадение
            $params = array_filter($matches); // Фильтруем пустые параметры
            
            [$class, $function] = $route['controller'];
            
            
            foreach ($route['middlewares'] as $middleware) {
                if (!$this->callMiddleware($middleware)) {
                    return; // Прерываем выполнение, если миддлвар не разрешил
                }
            }
            
            // Проверка существования контроллера и метода
            if (!class_exists($class) || !method_exists($class, $function)) {
                http_response_code(500);
                echo "Internal Server Error";
                return;
            }

            $controllerInstance = new $class;

            // Вызов функции контроллера с переданными параметрами
            $controllerInstance->{$function}(...array_values($params));
            return; // Завершаем выполнение после нахождения маршрута
        }

        // Обработка случая, если ни один маршрут не подошел
        http_response_code(404);
        echo "Page not found";
    }

    private function callMiddleware($middleware) {
        [$class, $method] = $middleware;
        $middlewareInstance = new $class;
        return $middlewareInstance->{$method}(); // Предполагается, что метод возвращает true/false
    }

}


// Пример использования:
//$router = new Router();
//$router->add('GET', '/user/{id}', [UserController::class, 'show']);

// А для запроса /user/123
//$router->dispatch('/user/123');