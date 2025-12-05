<?php

declare(strict_types=1);

/**
 * This file is part of the EaseCore package.
 *
 * (c) Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AbraFlexi\Zabbix;

require_once '../vendor/autoload.php';

\define('APP_NAME', 'AbraFlexi-System-Status');

// Get metric from command line args
$metric = $argv[1] ?? '';

try {
    // Parse command line arguments
    $options = getopt('m::e::', ['metric::', 'env::']);

    // Get the path to the .env file
    $envfile = $options['env'] ?? '../.env';
    \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

    // Get metric from command line if provided
    $requestedMetric = $options['metric'] ?? $metric;

    // Construct status URL
    $baseUrl = \Ease\Shared::cfg('ABRAFLEXI_URL');

    if (empty($baseUrl)) {
        throw new \Exception('ABRAFLEXI_URL not configured');
    }

    $checker = new \AbraFlexi\Status();
    $status = $checker->getData();
    // Add configuration information as additional metrics
    $status['configUrl'] = \Ease\Shared::cfg('ABRAFLEXI_URL');
    $status['configLogin'] = \Ease\Shared::cfg('ABRAFLEXI_LOGIN');

    // If specific metric requested, return only that value
    if (!empty($requestedMetric)) {
        if (!\array_key_exists($requestedMetric, $status)) {
            throw new \Exception("Metric '{$requestedMetric}' not found in status data");
        }

        $value = $status[$requestedMetric];

        // Convert specific values to numeric format for Zabbix
        switch ($requestedMetric) {
            case 'systemLoad':
                echo (float) $value;

                break;
            case 'memoryUsed':
            case 'memoryHeap':
            case 'bytesRead':
            case 'bytesWritten':
            case 'totalGcTime':
                echo (int) $value;

                break;
            case 'loggedUser':
            case 'loggedUserRO':
            case 'loggedUserRW':
            case 'sessions':
            case 'sessionsRO':
            case 'sessionsRW':
                echo (int) $value;

                break;
            case 'appServerRunning':
                echo ($value === 'true' || $value === true) ? 1 : 0;

                break;

            default:
                echo $value;
        }
    } else {
        // Return all metrics as JSON for debugging or bulk processing
        echo json_encode($status, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    exit(0);
} catch (\Exception $e) {
    // Log error and exit with error code
    error_log('AbraFlexi System Status Error: '.$e->getMessage());

    // Return appropriate default values for Zabbix based on metric type
    if (!empty($requestedMetric)) {
        switch ($requestedMetric) {
            case 'systemLoad':
                echo '0';

                break;
            case 'memoryUsed':
            case 'memoryHeap':
            case 'bytesRead':
            case 'bytesWritten':
            case 'totalGcTime':
            case 'loggedUser':
            case 'loggedUserRO':
            case 'loggedUserRW':
            case 'sessions':
            case 'sessionsRO':
            case 'sessionsRW':
                echo '0';

                break;
            case 'appServerRunning':
                echo '0';

                break;
            case 'version':
            case 'licenseName':
            case 'licenseVariant':
            case 'javaVersion':
            case 'operatingSystem':
            case 'uuid':
                echo 'unknown';

                break;

            default:
                echo '';
        }
    } else {
        echo json_encode([]);
    }

    exit(1);
}
