<?php

date_default_timezone_set("Europe/Berlin");
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ru_RU.UTF-8');
@session_start();

if (!defined('HOME')) {
    define('HOME', __DIR__);
}

spl_autoload_register(function($class){
    $parts = array_values(array_filter(explode('\\', $class)));
    $className = array_pop($parts);
    if (!empty($parts)) {
        require_once(HOME . '/includes/' . implode('/', $parts) . '/class.' . $className . '.php');
        return;
    }
    is_file(HOME . '/includes/class.' . $className . '.php') && require_once(HOME . '/includes/class.' . $className . '.php');
    is_file(HOME . '/includes/class.' . strtolower($className) . '.php') && require_once(HOME . '/includes/class.' . strtolower($className) . '.php');
    is_file(HOME . '/includes/Helpers/class.' . $className . '.php') && require_once(HOME . '/includes/Helpers/class.' . $className . '.php');
});

require_once(HOME . '/includes/class.IDNA.php');
require_once(HOME . '/includes/class.db.php');
