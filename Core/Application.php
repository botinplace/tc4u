<?php
namespace Core;

use Core\Config\Config;
use Core\Exceptions\ContainerException;
use RuntimeException;

class Application
{
    private Container $container;
    private Router $router;

    public function __construct()
    {
        $this->initializeContainer();
        $this->registerCoreServices();
    }

    private function initializeContainer(): void
    {
        if ($this->shouldUseCompiledContainer()) {
            $this->loadCompiledContainer();
        } else {
            $this->container = new Container();
        }
    }

    private function shouldUseCompiledContainer(): bool
    {
        return env('APP_ENV') === 'production' 
            && file_exists(APP_DIR.'/cache/CompiledContainer.php');
    }

    private function loadCompiledContainer(): void
    {
	$filePath = APP_DIR.'/cache/CompiledContainer.php';
        
	if (!file_exists($filePath)) {
    	    throw new ContainerException('Compiled container not found');
        }

        require_once $filePath;
    	$this->container = new \MainApp\cache\CompiledContainer();   
 }

    private function registerCoreServices(): void
    {
        // Базовые сервисы для всех окружений
        $this->container->singleton(Request::class, fn() => new Request());
        $this->container->singleton(Session::class, fn() => new Session());
        $this->container->bind(Router::class, fn($c) => new Router(
            $c,
            $c->get(Response::class)
        ));

		$this->container->singleton(Config::class, fn() => new Config(
			require CONFIG_DIR . '/config.php'
		));

		$this->container->bind(Cache::class, fn($c) =>  new Cache(
			$c->get(Config::class),
			CONFIG_DIR . '/routes.php'
		));
		  
        // Загрузка конфигурации DI
        $this->loadDiConfig();

        // Компиляция в production если нужно
        if (env('APP_ENV') === 'production' && !$this->container->isCompiled()) {
            $this->container->compile(APP_DIR.'/cache');
        }
    }

    private function loadDiConfig(): void
    {
        $diConfig = Config::get('di', []);

        foreach ($diConfig['bindings'] ?? [] as $abstract => $concrete) {
            $this->container->bind($abstract, $concrete);
        }

        foreach ($diConfig['singletons'] ?? [] as $abstract => $concrete) {
            $this->container->singleton($abstract, $concrete);
        }
    }

    private function dispatchCurrentRoute(): void
    {
        try {
            $request = $this->container->get(Request::class);
            $path = $this->sanitizePath($request::url());
            
            $this->router = $this->container->get(Router::class);
            $this->router->dispatch($path);

        } catch (\Throwable $e) {
            $this->handleError($e);
        }
    }

    private function sanitizePath(string $uri): string
    {
        $path = filter_var(trim($uri), FILTER_SANITIZE_URL);
        $path = explode("?", $path, 2)[0];

        // Обработка URI фиксера
        $path = $this->applyUriFixes($path);

        return $path ?: "/";
    }

    private function applyUriFixes(string $path): string
    {
        if ($uriFixer = Config::get('app.uri_fixer')) {
            $path = preg_replace(
                "/^" . preg_quote($uriFixer, "/") . '(\/|$)/', 
                "/", 
                $path
            );
        }

        if (defined('BASE_URL') && BASE_URL !== "") {
            $path = preg_replace(
                "/^\/" . preg_quote(BASE_URL, "/") . '(\/|$)/',
                "/",
                $path
            );
        }

        return $path;
    }

    private function handleError(\Throwable $e): void
    {
        $this->logError($e);
        
        if (env('APP_ENV') !== 'production') {
            $this->renderError($e);
        }

        http_response_code(500);
        exit;
    }

    private function logError(\Throwable $e): void
    {
        error_log(sprintf(
            "[%s] ERROR: %s in %s:%d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    private function renderError(\Throwable $e): void
    {
        echo "<h1>Application Error</h1>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        
        if (Config::get('app.debug')) {
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
    }

    public function run(): void
    {        
        $this->dispatchCurrentRoute();
    }
}