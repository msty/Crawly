<?php

date_default_timezone_set("Europe/Berlin");
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ru_RU.UTF-8');
@session_start();

if (!defined('HOME')) {
    define('HOME', dirname(__FILE__));
}

spl_autoload_register(function($class){
    $parts = array_values(array_filter(explode('\\', $class)));
    $className = array_pop($parts);
    if (!empty($parts)) {
        require_once(HOME . '/' . implode('/', $parts) . '/' . $className . '.php');
        return;
    }
    is_file(HOME . '/' . $className . '.php') && require_once(HOME . '/' . $className . '.php');
    is_file(HOME . '/Helpers/' . $className . '.php') && require_once(HOME . '/Helpers/' . $className . '.php');
});

require_once(HOME . '/../../vendor/IDNA/IDNA.php');
require_once(HOME . '/functions.php');
