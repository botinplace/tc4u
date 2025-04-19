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
    'app' => [
        'name' => 'Название Проекта',
        'env' => 'production',
        'uri_fixer' => '',
        'allowed_methods' => explode(',', env('ALLOWED_METHODS', 'GET,POST')),
        'auth_path' => env('AUTH_PATH', '/auth'),
    ],
    
    'database' => [
        'default' => env('DB_DRIVER', 'pgsql'),
		
        'connections' => [
            'pgsql' => [
                'host' => env('DB_HOST'),
                'name' => env('DB_NAME'),
                'user' => env('DB_USER'),
                'pass' => env('DB_PASS'),
            ],
            'mysql' => [
		'host' => env('DB_HOST'),
                'name' => env('DB_NAME'),
                'user' => env('DB_USER'),
                'pass' => env('DB_PASS'),
			],
        ],
    ],
    
    // Active Directory
    'active_directory' => [
        'servers' => [
            ['ip' => env('AD_SERVER_1'), 'domain' => env('AD_DOMAIN_1')],
            ['ip' => env('AD_SERVER_2'), 'domain' => env('AD_DOMAIN_2')],
        ],
    ],
    
    // Middleware по умолчанию
    'middleware' => [
	\MainApp\Middleware\MiddlewareClass1::class,
	\MainApp\Middleware\MiddlewareClass2::class,
	\MainApp\Middleware\MiddlewareClass3::class
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
                'title' => 'Главная страница',
                'description' => 'Описание главной страницы',
                'isHidden' => false,
                'parentId' => null,
                'layer'=>1,
            ],
    'about'=>[
                'id'=>2,
                'baseTemplate' => 'base',
                'contentFile' => 'About',       
                'title' => 'О нас',
                'description' => 'Описание страницы О нас',
                'isHidden' => false,
                'parentId' => null,
                'layer'=>1,
            ]
];
