# ts4u
The tc4u PHP framework

composer install --ignore-platform-reqs



## routes.php format

```php
<?php 
return [
    'home' => [        
        'path' => '/',
        'methods' => [
            'GET' => [
                'controller' => [MainApp\Controllers\IndexController::class, 'index'],
                'needauth' => false,
                'onlyforguest' => true,
            ],
            'POST' => [
                'controller' => [MainApp\Controllers\IndexController::class, 'postData'],
                'needauth' => true,
                'onlyforguest' => false,
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
        'onlyforguest' => false,
    ],
    'admin' => [
        'path' => '/admin',
        'methods' => [
            'GET' => [
                'controller' => [MainApp\Controllers\AdminController::class, 'dashboard'],
            ],
            'POST' => [
                'controller' => [MainApp\Controllers\AdminController::class, 'manage'],
            ],
        ],
        'needauth' => true,
        'onlyforguest' => false,
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
            'needauth' => true,
            'onlyforguest' => false,
            ],
        ],
    ],
    'logout' => [
        'path' => '/logout',
        'methods' => [
            'POST' => [
                'controller' => [MainApp\Controllers\AuthController::class, 'logout'],
            ],
        ],
        'needauth' => true,
        'onlyforguest' => false,
    ],
    ...
];
