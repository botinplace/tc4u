<?php
namespace Core;

class Router7 {
    private $routes = [];

	public function set(array $routes){
		$this->routes = $routes;
	}
	
	public function saveRoutes($routes_path){
		$this->routes = $routes;
	}
	
    public function add(string $method, string $path, array $controller, ?string $template): void {
        $path = $this->normalizePath($path);
        $this->routes[] = [
            'path' => $path,
            'method' => strtoupper($method),
            'controller' => $controller,
			'template' => $template,
            'middlewares' => []
        ];
    }

    private function normalizePath(string $path): string {
        // Удаление лишних слешей и нормализация пути
        $path = trim($path, '/');
        $path = "/{$path}/"; 
        $path = preg_replace('#[/]{2,}#', '/', $path);
        return $path;
    }

 public function replacement($matches) {
    $paramName = $matches[1]; // имя параметра
    $paramType = $matches[2]; // тип параметра (int, str)
    $paramConstraints = isset($matches[4]) ? $matches[4] : ''; // ограничения, если есть

    switch ($paramType) {
        case 'int':
            // Если указаны ограничения, используем их
            return "(?P<{$paramName}>[0-9]{1," . ($paramConstraints ? $paramConstraints : '') . "})";
        case 'str':
            return "(?P<{$paramName}>[a-zA-Z0-9]{" . ($paramConstraints ? $paramConstraints : '1,}') . "})";
        default:
            return "(?P<{$paramName}>[^/]+)";
    }
}


    public function dispatch(string $path): void {
        $path = $this->normalizePath($path);
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['path']);
			//$pattern = '/\{(\w+):(\w+)(\[(.*?)\])?\}/';			
			//$compiledPath  = preg_replace_callback($pattern, $this->replacement() , $route['path']);
			//var_dump($compiledPath );

            if (!preg_match("#^{$pattern}$#", $path, $matches)) {			
                continue;
            }

            array_shift($matches); 
            $params = array_filter($matches); 
			
			if (empty($route['controller'][0])) {				
				$basename = ucfirst( basename($route['path']) );
				$route['controller'][0] = "MainApp\\Controllers\\{$basename}Controller";
			}

            [$class, $function] = $route['controller'];

            
            foreach ($route['middlewares'] as $middleware) {
                if (!$this->callMiddleware($middleware)) {
                    return;
                }
            }

            if (!class_exists($class) || !method_exists($class, $function)) {				
				[$class, $function] = ["Core\\Controller",'renderEmptyPage'];				
            }

            $controllerInstance = new $class('base','777');

            $controllerInstance->{$function}(...array_values($params));
            return;
        }

        http_response_code(404);
        echo "Page not found";
    }

    private function callMiddleware(array $middleware): bool {
        if (count($middleware) !== 2) {
            return false;
        }
        
        [$class, $method] = $middleware;
        if (!class_exists($class) || !method_exists($class, $method)) {
            return false;
        }

        $middlewareInstance = new $class();
        return $middlewareInstance->{$method}();
    }
}