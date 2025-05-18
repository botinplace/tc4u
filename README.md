# ts4u
The tc4u PHP framework

## Установка
git clone / composer require

Если версия php ниже нужной:
composer install --ignore-platform-reqs

## Структура фреймворка
```plaintext
/Core
│
├── /Ad
│   └── [LDAP]
│
├── /Auth
│   └── [Классы для аутентификации и авторизации пользователей]
│
├── /Config
│   └── [Конфигурационные файлы и классы для загрузки конфигурации]
│
├── /Database
│   └── [Классы для работы с базами данных, например, PostgreSQL.php, DatabaseFactory.php ,DatabaseInterface.php]
│
├── /Middlewares
│   └── [Папка для хранения различных промежуточных обработчиков запросов]
│
├── Application.php         // Основной класс приложения, управляющий жизненным циклом
├── Cache.php               // Класс для работы с кэшированием
├── Controller.php          // Базовый класс контроллера, от которого наследуются все контроллеры
├── Cookie.php              // Класс для работы с Cookie файлами
├── ErrorLogger.php         // Класс для логирования ошибок
├── FileManager.php         // Класс для работы с файлами
├── Middleware.php          // Базовый класс для промежуточных обработчиков запросов
├── Model.php               // Базовый класс модели, от которого наследуются все модели
├── Request.php             // Класс для обработки HTTP-запросов
├── Response.php            // Класс для формирования HTTP-ответов
├── Route.php               // Класс, представляющий маршрут
├── Router.php              // Класс маршрутизатора, который управляет маршрутизацией
├── Session.php             // Класс для работы с сессиями
└── TemplateEngine.php      // Класс шаблонизатора
 ```

## Базовая структура каталогов приложения

- **MainApp/**
    - **Config/**
       - **config.php**
       - **pagedata.php**
       - **routes.php**
    - **Content/**
    - **Controllers/**
    - **Models/**
    - **Middlewares/**
    - **Migrations/**
    - **Repositories/**
    - **Templates/**
       - **base.php**
    - **Logs/** 
    - **public/**        
       - **.htaccess**
       - **index.php**

## config.php format
```php
return [
    /* Application Settings */
    'app' => [
        'name' => env('APP_NAME', 'My Application'),
        'env' => env('APP_ENV', 'production'),
        'debug' => (bool)env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost'),
        'uri_prefix' => env('URI_PREFIX', ''),
        'uri_fixer' => env('URI_PREFIX', ''),
        'timezone' => env('APP_TIMEZONE', 'Europe/Moscow'),
        'locale' => env('APP_LOCALE', 'ru'),
        'faker_locale' => env('FAKER_LOCALE', 'ru_RU'),
        'allowed_methods' => explode(',', env('ALLOWED_METHODS', 'GET,POST')),
        'auth' => [
            'path' => env('AUTH_PATH', '/auth'),
            'timeout' => (int)env('AUTH_TIMEOUT', 120),
            'ldap_enabled' => (bool)env('LDAP_ENABLED', false), // По умолчанию лучше false
        ],
    ],

    /* Database Configuration */
    'database' => [
        'default' => env('DB_CONNECTION', 'pgsql'),

        'connections' => [
            'default' => [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', 'localhost'),
                'port' => env('DB_PORT', '5432'),
                'database' => env('DB_DATABASE', ''),
                'username' => env('DB_USERNAME', ''),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'schema' => 'public',
                'sslmode' => 'prefer',
            ],

            'monitor' => [
                'driver' => 'pgsql',
                'host' => env('DB_MONITOR_HOST', ''),
                'database' => env('DB_MONITOR_NAME', ''),
                'username' => env('DB_MONITOR_USER', ''),
                'password' => env('DB_MONITOR_PASS', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ],

            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', 'localhost'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', ''),
                'username' => env('DB_USERNAME', ''),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
            ],
        ],

        'migrations' => 'migrations',
    ],

    /* LDAP Configuration */
    'ldap' => [
        'enabled' => (bool)env('LDAP_ENABLED', false),
        'log' => (bool)env('LDAP_LOG', false),
        'cache' => (bool)env('LDAP_CACHE', true),

        'connections' => [
            'default' => [
                'hosts' => array_filter([
                    env('AD_SERVER_1', ''),
                    env('AD_SERVER_2', '')
                ]),
                'base_dn' => env('AD_BASE_DN', ''),
                'username' => env('AD_USERNAME', ''),
                'password' => env('AD_PASSWORD', ''),
                'port' => (int)env('AD_PORT', 389),
                'timeout' => (int)env('AD_TIMEOUT', 5),
                'use_ssl' => (bool)env('AD_USE_SSL', false),
                'use_tls' => (bool)env('AD_USE_TLS', true),
                'version' => (int)env('AD_VERSION', 3),
                'domain' => env('AD_DOMAIN', ''),
            ],
        ],
    ],

    /* Middleware Configuration */
    'middleware' => [
        'global' => [
            \MainApp\Middlewares\AuthViewMiddleware::class,
        ],
        'groups' => [
            'web' => [],
            'api' => [],
        ],
        'aliases' => [],
    ],

    /* Cache Configuration */
    'cache' => [
        'default' => env('CACHE_DRIVER', 'file'),
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__.'/../../storage/cache',
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'default',
            ],
        ],
        'prefix' => env('CACHE_PREFIX', 'app_cache'),
    ],

    /* Session Configuration */
    'session' => [
        'driver' => env('SESSION_DRIVER', 'file'),
        'lifetime' => (int)env('SESSION_LIFETIME', 120),
        'expire_on_close' => (bool)env('SESSION_EXPIRE_ON_CLOSE', false),
        'encrypt' => (bool)env('SESSION_ENCRYPT', false),
        'files' => __DIR__.'/../../storage/sessions',
        'cookie' => env('SESSION_COOKIE', 'app_session'),
    ],
];
```

## routes.php format

групп пока что нет

```php
<?php 
return [

    'home' => [        
        'path' => '/',
        'methods' => [
                        'GET' => [
                            'controller' => [MainApp\Controllers\IndexController::class, 'index'],
                        ],
                        'POST' => [
                            'controller' => [MainApp\Controllers\IndexController::class, 'postData'],
                            'middlewares' => [ MainApp\Middlewares\CsrfMiddleware::class ]
                        ],
                    ],
                ],

    'about' => [
        'path' => '/about',
        'methods' => [
                        'GET' => [
                            'controller' => [MainApp\Controllers\AboutController::class, 'show'],
                            'needauth' => false,
                            'onlyforguest' => true,
                        ],
                    ],
                ],

    'contact' => [
        'path' => '/contact2',
        'methods' => [
            'GET' => [
                'controller' => [MainApp\Controllers\ContactController::class, 'show'],
            ],
            'POST' => [
                'controller' => [MainApp\Controllers\ContactController::class, 'send'],
            ],
        ],
        'needauth' => true,
        'onlyforguest' => false,
    ],

    'profile' => [
        'path' => '/profile',
        'methods' => [
            'GET' => [
                'controller' => [MainApp\Controllers\ProfileController::class, 'view'],
            ],
            'POST' => [
                'controller' => [MainApp\Controllers\ProfileController::class, 'update'],
            ],
        ],
        'needauth' => true,
    ],

    'login' => [
        'path' => '/login',
        'methods' => [
            'GET' => [
                'controller' => [MainApp\Controllers\AuthController::class, 'showLoginForm'],
            'needauth' => true,
            'onlyforguest' => false,
            ],
            'POST' => [
                'controller' => [MainApp\Controllers\AuthController::class, 'login'],
            'needauth' => false,
            'onlyforguest' => true,
            ],
        ],
    ],

    'logout' => [
        'path' => '/logout',
        'methods' => [
            'GET' => [
                'controller' => [MainApp\Controllers\AuthController::class, 'logout'],
            ],
        ],
    ],
    ...
];

```

## pagedata.php format

```php
return [
    'home'=>[
                'id'=>1,
                'baseTemplate' => null,
                'contentFile' => 'Index',        
                'pagetitle' => 'Главная страница',
                'description' => 'Описание главной страницы',
                'isHidden' => false,
                'parentId' => null,
                'layer'=>1,
            ],
    'about'=>[
                'id'=>2,
                'baseTemplate' => 'base',
                'contentFile' => 'About',       
                'pagetitle' => 'О нас',
                'description' => 'Описание страницы О нас',
                'isHidden' => false,
                'parentId' => null,
                'layer'=>1,
            ]
];
