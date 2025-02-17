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
├── /DB
│   └── [Классы для работы с базами данных, например, DB.php, DatabaseInterface.php]
│
├── /Middlewares
│   └── [Папка для хранения различных промежуточных обработчиков запросов]
│
├── Application.php         // Основной класс приложения, управляющий жизненным циклом
├── Cache.php               // Класс для работы с кэшированием
├── Controller.php          // Базовый класс контроллера, от которого наследуются все контроллеры
├── ErrorLogger.php         // Класс для логирования ошибок
├── FileManager.php         // Класс для работы с файлами
├── Middleware.php          // Базовый класс для промежуточных обработчиков запросов
├── Model.php               // Базовый класс модели, от которого наследуются все модели
├── Request.php             // Класс для обработки HTTP-запросов
├── Response.php            // Класс для формирования HTTP-ответов
├── Route.php               // Класс, представляющий маршрут
└── Router.php              // Класс маршрутизатора, который управляет маршрутизацией
 ```

## Базовая структура каталогов приложения

- **MainApp/**
    - **Config/**
       - **config.php**
       - **pagedata.php**
       - **routes.php**
    - **Content/**
    - **Controllers/**
    - **Templates/**
       - **base.php**
    - **Logs/** 
    - **public/**        
       - **.htaccess**
       - **index.php**

## config.php format
```php
<?php
//
define('URI_FIXER', '');

define('POSTGRESQL_HOST', 'localhost');
define('POSTGRESQL_NAME', 'name');
define('POSTGRESQL_USER', 'user');
define('POSTGRESQL_PASS', 'password');

define('MYSQL_HOST', 'localhost');
define('MYSQL_NAME', 'name');
define('MYSQL_USER', 'user');
define('MYSQL_PASS', 'password');

define('SQLi_PATH',  dirname( ROOT.'core/app/config.php' ).'/ClassSQLi/SQLi_DB/' );
define('SQLi_NAME', 'site.db');
define('SQLi_USER', 'user');
define('SQLi_PASS', 'password');

// Адрес АД
define( 'AD_SERVER_IP', '255.255.255.255' );
define( 'AD_SERVER_IP2', '255.255.255.255' );
define( 'AD_DOMAIN', 'domain1' );
define( 'AD_DOMAIN2', 'domain2' );

// Доступные методы
define( 'ALLOWED_METHODS', 'GET,POST,PUT,DELETE,OPTIONS,HEAD' );

// Страница авторизации (если пользователь не авторизован)
define('AUTH_PATH','/auth');

// project_db=> Mssql Mysql Postgre SQLi NoDB
return (object) array(
	'defaultMiddlewares'=>[ ['Middleware','Method'] ],
    'project_db' => 'Postgre'
);
```

## routes.php format

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
