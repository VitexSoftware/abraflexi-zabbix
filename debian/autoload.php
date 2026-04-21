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

require_once '/usr/share/php/Composer/InstalledVersions.php';

(function (): void {
    $versions = [];
    foreach (\Composer\InstalledVersions::getAllRawData() as $d) {
        $versions = array_merge($versions, $d['versions'] ?? []);
    }
    $name    = defined('APP_NAME') ? APP_NAME    : 'unknown';
    $version = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
    $versions[$name] = ['pretty_version' => $version, 'version' => $version,
        'reference' => null, 'type' => 'library', 'install_path' => __DIR__,
        'aliases' => [], 'dev_requirement' => false];
    \Composer\InstalledVersions::reload([
        'root' => ['name' => $name, 'pretty_version' => $version, 'version' => $version,
            'reference' => null, 'type' => 'project', 'install_path' => __DIR__,
            'aliases' => [], 'dev' => false],
        'versions' => $versions,
    ]);
})();
