<?php
// MainApp/Config/config.php
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
			\MainApp\Middleware\AuthViewMiddleware::class
    ],
];
