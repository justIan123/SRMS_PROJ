<?php

require_once __DIR__ . '/bootstrap.php';

/*
|--------------------------------------------------------------------------
| DIRECTORY SEPARATOR
|--------------------------------------------------------------------------
*/
define('DS', DIRECTORY_SEPARATOR);

/*
|--------------------------------------------------------------------------
| PROJECT ROOT
|--------------------------------------------------------------------------
*/
define('SITE_ROOT', dirname(__FILE__, 2));

/*
|--------------------------------------------------------------------------
| SYSTEM PATHS
|--------------------------------------------------------------------------
*/
define('APP_PATH', SITE_ROOT . DS . 'app');
define('COMPONENTS_PATH', SITE_ROOT . DS . 'components');
define('CONFIG_PATH', SITE_ROOT . DS . 'config');
define('FUNCTIONS_PATH', SITE_ROOT . DS . 'functions');
define('HANDLERS_PATH', SITE_ROOT . DS . 'handlers');
define('INCLUDE_PATH', SITE_ROOT . DS . 'include');
define('PUBLIC_PATH', SITE_ROOT . DS . 'public');
define('IMAGES_PATH', SITE_ROOT . DS . 'images');
define('VENDOR_PATH', SITE_ROOT . DS . 'vendor');
define('DEV_PATH', SITE_ROOT . DS . 'dev');

define('DEBUG_PATH', DEV_PATH . DS . 'debug' . DS);
define('LOGS_PATH', DEV_PATH . DS . 'logs' . DS);

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/
define('DB_PATH', CONFIG_PATH . DS . 'db_supabase.php');

/*
|--------------------------------------------------------------------------
| APP SETTINGS
|--------------------------------------------------------------------------
*/
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');

define(
    'APP_DEBUG',
    filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL)
);

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
*/
switch (APP_ENV) {

    case 'development':
        define('BASE_URL', 'http://localhost/1_SRMS');
        break;

    case 'production':
        define('BASE_URL', 'https://' . $_SERVER['HTTP_HOST']);
        break;

    case 'desktop':
        define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);
        break;

    default:
        define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);
}

/*
|--------------------------------------------------------------------------
| URL PATHS
|--------------------------------------------------------------------------
*/
define('APP_URL', BASE_URL . '/app/');
define('COMPONENTS_URL', BASE_URL . '/components/');
define('HANDLERS_URL', BASE_URL . '/handlers/');
define('IMAGES_URL', BASE_URL . '/images/');
define('PUBLIC_URL', BASE_URL . '/public/');

/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/*
|--------------------------------------------------------------------------
| TIMEZONE
|--------------------------------------------------------------------------
*/
date_default_timezone_set('Asia/Manila');