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
        require_once(HOME . '/includes/' . implode('/', $parts) . '/' . $className . '.php');
        return;
    }
    is_file(HOME . '/includes/' . $className . '.php') && require_once(HOME . '/includes/' . $className . '.php');
    is_file(HOME . '/includes/Helpers/' . $className . '.php') && require_once(HOME . '/includes/Helpers/' . $className . '.php');
});

require_once(HOME . '/includes/IDNA.php');
require_once(HOME . '/includes/functions.php');
