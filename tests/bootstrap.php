<?php
/**
 * PHPUnit Bootstrap for AbraFlexi Zabbix tests
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Set test environment variables using putenv for actual environment
if (!getenv('ABRAFLEXI_URL')) {
    putenv('ABRAFLEXI_URL=https://demo.flexibee.eu:5434');
    $_ENV['ABRAFLEXI_URL'] = 'https://demo.flexibee.eu:5434';
}
if (!getenv('ABRAFLEXI_LOGIN')) {
    putenv('ABRAFLEXI_LOGIN=winstrom');
    $_ENV['ABRAFLEXI_LOGIN'] = 'winstrom';
}
if (!getenv('ABRAFLEXI_PASSWORD')) {
    putenv('ABRAFLEXI_PASSWORD=winstrom');
    $_ENV['ABRAFLEXI_PASSWORD'] = 'winstrom';
}

define('TEST_ENV', true);