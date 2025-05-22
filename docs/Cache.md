# Документация модуля кэширования Cache


##Важные настройки в php.ini
```ini
[apcu]
; Максимальный размер памяти для кэша (например, 128M)
apc.shm_size=128M
; Включить APCu
apc.enabled=1
```


```php
$cache = new Cache('routes_cache', '/path/to/routes.php', 3600);
$routes = $cache->load();

if (empty($routes)) {
    $routes = require $this->sourceFile;
    $cache->save($routes);
}
```