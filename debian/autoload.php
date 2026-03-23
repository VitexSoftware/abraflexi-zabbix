<?php
// Debian autoloader for abraflexi-zabbix
require_once '/usr/share/php/AbraFlexi/autoload.php';

spl_autoload_register(function ($class) {
    if (strpos($class, 'AbraFlexi\\Zabbix\\') === 0) {
        $relative_class = substr($class, strlen('AbraFlexi\\Zabbix\\'));
        $file = '/usr/lib/abraflexi-zabbix/AbraFlexi/Zabbix/' . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
            return true;
        }
    }
});
