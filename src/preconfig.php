<?php

date_default_timezone_set("Europe/Berlin");
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ru_RU.UTF-8');
@session_start();

if (!defined('HOME')) {
    define('HOME', dirname(__DIR__));
}

spl_autoload_register(function($class){
    $parts = array_values(array_filter(explode('\\', $class)));
    $className = array_pop($parts);
    if (!empty($parts)) {
        require_once(HOME . '/src/' . implode('/', $parts) . '/' . $className . '.php');
        return;
    }
    is_file(HOME . '/src/' . $className . '.php') && require_once(HOME . '/src/' . $className . '.php');
    is_file(HOME . '/src/Helpers/' . $className . '.php') && require_once(HOME . '/src/Helpers/' . $className . '.php');
});

require_once(HOME . '/vendor/IDNA/IDNA.php');
require_once(HOME . '/src/functions.php');
