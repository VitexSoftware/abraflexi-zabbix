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

\define('APP_NAME', 'AbraFlexi-Cached-Status');
\define('CACHE_TTL', 30); // Cache for 30 seconds
\define('CACHE_DIR', sys_get_temp_dir().'/abraflexi-zabbix-cache');

/**
 * Cached system status retrieval with smart caching.
 *
 * @param string $metric Optional specific metric to retrieve
 */
function getCachedSystemStatus(string $metric = ''): void
{
    try {
        // Parse command line arguments
        $options = getopt('m::e::', ['metric::', 'env::']);

        // Get the path to the .env file
        $envfile = $options['env'] ?? '../.env';
        \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

        // Get metric from command line if provided
        $requestedMetric = $options['metric'] ?? $metric;

        // Setup cache
        $cacheFile = CACHE_DIR.'/status_cache.json';
        $lockFile = CACHE_DIR.'/status_cache.lock';

        // Ensure cache directory exists
        if (!is_dir(CACHE_DIR)) {
            mkdir(CACHE_DIR, 0o755, true);
        }

        $cachedData = getCachedData($cacheFile);

        if ($cachedData === null) {
            // Cache miss - fetch fresh data with file locking to prevent concurrent requests
            $cachedData = fetchAndCacheData($cacheFile, $lockFile);
        }

        // Return requested metric or all data
        if (!empty($requestedMetric)) {
            if (!\array_key_exists($requestedMetric, $cachedData)) {
                throw new \Exception("Metric '{$requestedMetric}' not found in cached data");
            }

            $value = $cachedData[$requestedMetric];

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
            // Return all metrics as JSON
            echo json_encode($cachedData, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        }

        exit(0);
    } catch (\Exception $e) {
        // Log error and exit with appropriate default
        error_log('AbraFlexi Cached Status Error: '.$e->getMessage());

        if (!empty($requestedMetric)) {
            echo getDefaultValue($requestedMetric);
        } else {
            echo json_encode([]);
        }

        exit(1);
    }
}

/**
 * Get cached data if valid.
 */
function getCachedData(string $cacheFile): ?array
{
    if (!file_exists($cacheFile)) {
        return null;
    }

    $cacheTime = filemtime($cacheFile);
    $currentTime = time();

    // Check if cache is still valid
    if (($currentTime - $cacheTime) > CACHE_TTL) {
        return null;
    }

    $cachedContent = file_get_contents($cacheFile);

    if ($cachedContent === false) {
        return null;
    }

    $data = json_decode($cachedContent, true);

    return \is_array($data) ? $data : null;
}

/**
 * Fetch fresh data and cache it with file locking.
 */
function fetchAndCacheData(string $cacheFile, string $lockFile): array
{
    // Acquire lock to prevent multiple concurrent requests
    $lockHandle = fopen($lockFile, 'cb');

    if (!$lockHandle || !flock($lockHandle, \LOCK_EX | \LOCK_NB)) {
        // Another process is fetching - wait a bit and try cache again
        if ($lockHandle) {
            fclose($lockHandle);
        }

        usleep(100000); // Wait 100ms

        $cachedData = getCachedData($cacheFile);

        if ($cachedData !== null) {
            return $cachedData;
        }

        // Still no cache - throw error to avoid infinite waiting
        throw new \Exception('Could not acquire lock and no cached data available');
    }

    try {
        // Double-check cache while we have the lock
        $cachedData = getCachedData($cacheFile);

        if ($cachedData !== null) {
            return $cachedData;
        }

        // Fetch fresh data
        $checker = new \AbraFlexi\Status();
        $status = $checker->getData();

        if (!\is_array($status)) {
            throw new \Exception('Failed to retrieve status data from AbraFlexi API');
        }

        // Add configuration information as additional metrics
        $status['configUrl'] = \Ease\Shared::cfg('ABRAFLEXI_URL');
        $status['configLogin'] = \Ease\Shared::cfg('ABRAFLEXI_LOGIN');

        // Cache the data
        $cacheContent = json_encode($status, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        file_put_contents($cacheFile, $cacheContent, \LOCK_EX);

        // Set cache file timestamp
        touch($cacheFile);

        return $status;
    } finally {
        // Always release lock
        flock($lockHandle, \LOCK_UN);
        fclose($lockHandle);
        @unlink($lockFile);
    }
}

/**
 * Get default value for a metric when error occurs.
 */
function getDefaultValue(string $metric): string
{
    switch ($metric) {
        case 'systemLoad':
            return '0';
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
            return '0';
        case 'appServerRunning':
            return '0';
        case 'version':
        case 'licenseName':
        case 'licenseVariant':
        case 'javaVersion':
        case 'operatingSystem':
        case 'uuid':
        case 'configUrl':
        case 'configLogin':
            return 'unknown';

        default:
            return '';
    }
}

// Get metric from command line args
$metric = $argv[1] ?? '';
getCachedSystemStatus($metric);
