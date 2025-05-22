# Документация контейнера внедрения зависимостей Container

## Оглавление
1. [Введение](#введение)
2. [Быстрый старт](#быстрый-старт)
3. [Основные возможности](#основные-возможности)
4. [Методы контейнера](#методы-контейнера)
5. [Использование в продакшене](#использование-в-продакшене)
6. [Обработка ошибок](#обработка-ошибок)
7. [Примеры использования](#примеры-использования)

---

## Введение
Контейнер внедрения зависимостей реализует:
- PSR-11 (ContainerInterface)
- Автоматическое разрешение зависимостей
- Управление жизненным циклом объектов
- Тегирование сервисов
- Компиляцию для оптимизации

**Особенности:**
- Проверка циклических зависимостей
- Валидация типов параметров
- Кеширование рефлексии
- Интеграция с PSR-3 логгерами

---

## Быстрый старт
```php
use Core\Container;
use Monolog\Logger;

// Инициализация
$logger = new Logger('app');
$container = new Container($logger);

// Регистрация сервиса
$container->singleton(Database::class, MySQLDatabase::class);

// Получение объекта
$db = $container->get(Database::class);
```

---

## Основные возможности

### 1. Автоматическое связывание
```php
// Автоматическое разрешение зависимостей
class UserService {
    public function __construct(
        private Database $db,
        private LoggerInterface $logger
    ) {}
}

$service = $container->get(UserService::class);
```

### 2. Синглтоны
```php
$container->singleton(Cache::class, RedisCache::class);
$cache1 = $container->get(Cache::class);
$cache2 = $container->get(Cache::class); // $cache1 === $cache2
```

### 3. Тегирование сервисов
```php
$container->tag([Logger::class, Mailer::class], 'infrastructure');
$services = $container->getTagged('infrastructure');
```

### 4. Компиляция
```php
$container->compile(__DIR__.'/cache');
// Генерирует оптимизированный CompiledContainer
```

---

## Методы контейнера

### `bind(string $abstract, mixed $concrete = null, bool $singleton = false)`
Регистрирует привязку в контейнере.

```php
$container->bind(LoggerInterface::class, FileLogger::class);
```

### `singleton(string $abstract, mixed $concrete = null)`
Регистрирует синглтон.

```php
$container->singleton(Database::class, MySQLDatabase::class);
```

### `tag(array $services, string $tag)`
Группирует сервисы по тегу.

```php
$container->tag([ServiceA::class, ServiceB::class], 'workers');
```

### `getTagged(string $tag): array`
Возвращает все сервисы с указанным тегом.

```php
$workers = $container->getTagged('workers');
```

### `compile(string $cacheDir)`
Генерирует оптимизированную версию контейнера.

---

## Использование в продакшене

### Конфигурация
```php
// Регистрация логгера
$container->singleton(LoggerInterface::class, Monolog\Logger::class);

// Компиляция при деплое
if ($env === 'production') {
    $container->compile(__DIR__.'/var/cache');
}
```

### Рекомендации
1. Всегда используйте CompiledContainer в продакшене
2. Настройте OPcache для кеширования PHP-кода
3. Регистрируйте тяжелые сервисы как синглтоны
4. Обрабатывайте исключения при создании объектов

---

## Обработка ошибок

**Типы исключений:**
- `RuntimeException` - общие ошибки
- `ReflectionException` - проблемы с рефлексией
- `TypeError` - несоответствие типов

**Пример обработки:**
```php
try {
    $service = $container->get(SomeService::class);
} catch (\Throwable $e) {
    // Логирование и обработка ошибки
}
```

---

## Примеры использования

### 1. Регистрация фабрики
```php
$container->bind('config', function() {
    return require __DIR__.'/config.php';
});
```

### 2. Внедрение параметров
```php
$mailer = $container->make(Mailer::class, [
    'timeout' => 30,
    'retries' => 3
]);
```

### 3. Работа с интерфейсами
```php
$container->bind(LoggerInterface::class, CloudLogger::class);
$logger = $container->get(LoggerInterface::class);
```

### 4. Кастомные зависимости
```php
class AuthService {
    public function __construct(
        private string $apiKey,
        private int $timeout = 10
    ) {}
}

$auth = $container->make(AuthService::class, [
    'apiKey' => 'your-secret-key'
]);
```

---

**Системные требования:**
- PHP 8.1+
- Включенный OPcache (рекомендуется)
- PSR-3 совместимый логгер

**Лицензия:** MIT