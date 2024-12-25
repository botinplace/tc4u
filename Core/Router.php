<?php
namespace Core;

class Router {
    private $routes = [];
	private $pageData = []; // path,title,content,parents,
    private $cacheFile;
	private $routesFile;
	private $app;

    public function __construct(Application $app ) {
		$this->app = $app;
		$this->routesFile = APP . 'routes.php';
		$this->cacheFile = APP . 'cache/cachroutes.php';
		
		$routes = file_exists($this->routesFile) ? require $this->routesFile : [];
        $this->loadRoutesFromCache();
		
		$this->setRoutes($routes);
    }
	
	public function setRoutes($routes){		
		if (!empty($this->routes)) return;
		foreach ($routes as $route) {
			$this->addRoute(
										$route['id'],
										$route['method'],
										$route['path'],
										$route['controller'],
										$route['basetemplate'] ?? null,
										$route['contentfile'] ?? null,
										$route['needauth'] ?? null,
										$route['parentid'] ?? null
									);
		}
		$this->cachRoutes();
	}
	
    public function cachRoutes() {
		file_put_contents($this->cacheFile, '<?php return ' . $this->arrayToShortSyntax($this->routes) . ';');
		
    }
	
	private function arrayToShortSyntax($array){
        //return str_replace('array (', '[', str_replace(')', ']', var_export($array, true)));
		$arrayString = var_export($array, true);
		$arrayString = str_replace('array (', '[', $arrayString);
		$arrayString = str_replace(')', ']', $arrayString);		
		$arrayString = preg_replace('/\s*\d+\s*=>/', '', $arrayString);
		$arrayString = preg_replace('/,\s*+/', ',', $arrayString);
		$arrayString = preg_replace('/\[\s*,/', '[', $arrayString);
		$arrayString = preg_replace('/,\s*\]/', ']', $arrayString);
		return $arrayString;
    }

private function loadRoutesFromCache() {
    if (!file_exists($this->cacheFile) || !file_exists($this->routesFile)) {
        return;
    }

    $cacheFileTime = filemtime($this->cacheFile);
    $routesFileTime = filemtime($this->routesFile);

    if ($cacheFileTime < $routesFileTime) {
        $this->clearCache();
    } else {
        $this->loadRoutesFromCacheFile();
    }
}

private function clearCache() {
    if (file_exists($this->cacheFile)) {
        unlink($this->cacheFile);
    }
}

private function loadRoutesFromCacheFile() {
    try {
        $this->routes = require $this->cacheFile; 
    } catch (\Throwable $e) {        
        $this->routes = [];
    }
}

    public function addRoute(int $id,string $method, string $path, array $controller, ?string $template,?string $contentfile,?bool $needauth): void {
        $path = $this->normalizePath($path);
        $this->routes[] = [
			'id' => $id,
            'path' => $path,
            'method' => strtoupper($method),
            'controller' => $controller,
            'basetemplate' => $template,
			'contentfile' => $contentfile,
            'middlewares' => [],
			'needauth' => $needauth,
			'parentid'=>''
        ];
    }

    private function normalizePath(string $path): string {
        $path = trim($path, '/');		
		return '/' . preg_replace('#[/]{2,}#', '/', $path);		
    }

    public function dispatch(string $path): void {
        $path = $this->pageData['path'] = $this->normalizePath($path);
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['path']);
            if (!preg_match("#^{$pattern}$#", urldecode($path), $matches)) {
                continue;
            }

            array_shift($matches); 
            $params = array_filter($matches); 
            $this->handleRoute($route, $params);
            return;
        }

		$this->handleRoute([],[]);
        //http_response_code(404);
        //echo "Page not found";		
    }

    private function handleRoute(array $route, array $params): void {
		
		if(empty($route)){
			$route=[
				'id' => 0,
				'path' => '404',
				'method' => '',
				'controller' => ["Core\\Controller",'handleNotFound'],
				'basetemplate' => '',
				'contentfile' => '404',
				'middlewares' => [],
				'needauth' => false,
				'parentid'=>''
			];
		}
		
		if ($route['needauth'] && !$this->isUserAuthenticated()) {
			header('X-Auth-Required: true');
			header('Location: auth');
			exit();
		}
		
        [$class, $function] = $this->resolveController($route);
		
		if (!class_exists($class) || !method_exists($class, $function)) {
			[$class, $function] = ["Core\\Controller",'renderEmptyPage'];				
        }
		
		if(isset($route['middlewares'])){
			foreach ($route['middlewares'] as $middleware) {
				if (!$this->callMiddleware($middleware)) {
					return;
				}
			}
		}
		
		$basename = ucfirst(basename($route['path']));
		$this->pageData['basetemplate'] = isset($route['basetemplate']) ? $route['basetemplate'] : 'base';
		$this->pageData['contentFile'] = isset($route['contentfile']) ? $route['contentfile'] : (!empty($basename) ? $basename : 'Index') ;
		$this->pageData['needauth'] = isset($route['needauth']) ? $route['needauth'] : false;

		ob_start();
        $controllerInstance = new $class($this->pageData);
        $controllerInstance->{$function}(...array_values($params));	
		//ob_get_clean();		
		return;
    }

    private function resolveController(array $route): array {
        if (empty($route['controller'][0])) {
            $basename = ucfirst(basename($route['path']));
            $route['controller'][0] = "MainApp\Controllers\\{$basename}Controller";
        }
        return $route['controller'];		
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
	
	
	private function isUserAuthenticated(): bool {
		return isset($_SESSION['user_id']);
	}
}
