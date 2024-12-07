<?php
//define('URI_FIXER', '/mytestproject');
//define('FIXED_URL', ( !empty(URI_FIXER) ? URI_FIXER.(!empty(BASE_URL) ? '/' : '') : '').BASE_URL );

define('POSTGRESQL_HOST', 'localhost');
define('POSTGRESQL_NAME', 'drp_project');
define('POSTGRESQL_USER', 'postgres');
define('POSTGRESQL_PASS', '1234');

define('MYSQL_HOST', 'localhost');
define('MYSQL_NAME', 'name');
define('MYSQL_USER', 'user');
define('MYSQL_PASS', 'pass');

define('SQLi_PATH',  dirname( ROOT.'core/app/config.php' ).'/ClassSQLi/SQLi_DB/' );
//define('SQLi_PATH', '/home/c5324/core.demo360.ru/core/app/ClassSQLi/SQLi_DB/');
define('SQLi_NAME', 'site.db');
define('SQLi_USER', 'user');
define('SQLi_PASS', 'pass');

// Адрес АД
//define( 'AD_SERVER_IP', 'xxx.xxx.x.x' );
define( 'AD_SERVER_IP', '192.168.7.99' );
define( 'AD_SERVER_IP2', '172.20.0.50' );
define( 'AD_DOMAIN', 'proletarsky' );
define( 'AD_DOMAIN2', 'cniism' );

// project_db=> Mssql Mysql Postgre SQLi NoDB
return (object) array(
    'host' => 'localhost',
    'username' => 'root',
    'testvar' => 'Текст переменной',
    'project_db' => 'Postgre'
);