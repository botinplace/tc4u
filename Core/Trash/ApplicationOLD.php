<?php
declare(strict_types=1);

require_once ROOT.'vendor/autoload.php';


$php_version = (int) substr(PHP_VERSION, 0, 3);

if ($php_version === 7) {
    require_once ROOT.'Core/Router7.php';
    $router = new Core\Router7(); // Создаем экземпляр класса для PHP 7
} elseif ($php_version === 8) {
    require_once ROOT.'Core/Router8.php';
    $router = new Core\Router8(); // Создаем экземпляр класса для PHP 8
} else {
    throw new Exception("Неподдерживаемая версия PHP");
}

/*
$routesCacheFile = ROOT . 'cache/routes.php';

if (file_exists($routesCacheFile)) {
    $routes = require $routesCacheFile;
} else {
    $routes = require APP.'routes.php';
    file_put_contents($routesCacheFile, '<?php return ' . var_export($routes, true) . ';');
}
*/


// Загрузка маршрутов из файла
$routes = require APP.'routes.php';

// Загрузка кофига
$config = require ROOT.'Core/Configs/Config.php';

// Создаем экземпляр маршрутизатора
//$router = new Router();

// Добавление маршрутов
foreach ($routes as $route) {
    $router->addRoute( $route['method'], $route['path'], $route['controller'], null );
}

// Получение текущего пути и метода
$path = filter_var( trim($_SERVER['REQUEST_URI']), FILTER_SANITIZE_URL);
$path = explode('?', $path, 2)[0];

// Удаление лишнего (например /admin/)
$path = (URI_FIXER !== '') ? preg_replace('/^' . preg_quote(URI_FIXER, '/') . '(\/|$)/', '/', $path) : $path;
$path = (BASE_URL !== '') ? preg_replace('/^\/' . preg_quote(BASE_URL, '/') . '(\/|$)/', '/', $path) : $path;
$path = $path ?: '/';

//$pattern = '/' . preg_quote(URI_FIXER, '/') . '\/|' . preg_quote(BASE_URL, '/') . '\//';
//$path = preg_replace($pattern, '/', filter_var(trim($_SERVER['REQUEST_URI']), FILTER_SANITIZE_URL));
//$path = explode('?', $path, 2)[0];
//$path = $path ?: '/';


//$method = $_SERVER['REQUEST_METHOD'];

// Запуск маршрутизации
try {
    $router->dispatch($path);
} catch (Exception $e) {
    echo 'Ошибка: ' . $e->getMessage();
}