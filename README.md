# ts4u
The tc4u PHP framework

composer install --ignore-platform-reqs

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
    - **public/**        
       - **.htaccess**
       - **index.php**
        

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
