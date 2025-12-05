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

\define('APP_NAME', 'AbraFlexi-Network-Check');

// Exit codes for granular error detection
const EXIT_SUCCESS = 0;
const EXIT_NETWORK_ERROR = 1;    // Network connectivity issues
const EXIT_AUTH_ERROR = 2;       // Authentication failure
const EXIT_SERVICE_ERROR = 3;    // Service unavailable/not running
const EXIT_DATA_ERROR = 4;       // Invalid response data

/**
 * Perform comprehensive network and service check for AbraFlexi.
 *
 * @param string $checkType Type of check: 'network', 'auth', 'service', or 'all'
 */
function performNetworkCheck(string $checkType = 'all'): void
{
    try {
        // Parse command line arguments
        $options = getopt('t::e::', ['type::', 'env::']);

        // Get the path to the .env file
        $envfile = $options['env'] ?? '../.env';
        \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

        // Get check type from command line if provided
        $requestedCheck = $options['type'] ?? $checkType;

        $baseUrl = \Ease\Shared::cfg('ABRAFLEXI_URL');

        if (empty($baseUrl)) {
            echo '0'; // Configuration error

            exit(EXIT_SERVICE_ERROR);
        }

        $results = [];

        // Test 1: Basic Network Connectivity
        if ($requestedCheck === 'network' || $requestedCheck === 'all') {
            $networkResult = testNetworkConnectivity($baseUrl);
            $results['network'] = $networkResult;

            if ($requestedCheck === 'network') {
                echo $networkResult ? '1' : '0';

                exit($networkResult ? EXIT_SUCCESS : EXIT_NETWORK_ERROR);
            }
        }

        // Test 2: Authentication
        if ($requestedCheck === 'auth' || $requestedCheck === 'all') {
            $authResult = testAuthentication($baseUrl);
            $results['auth'] = $authResult;

            if ($requestedCheck === 'auth') {
                echo $authResult ? '1' : '0';

                exit($authResult ? EXIT_SUCCESS : EXIT_AUTH_ERROR);
            }
        }

        // Test 3: Service Health
        if ($requestedCheck === 'service' || $requestedCheck === 'all') {
            $serviceResult = testServiceHealth($baseUrl);
            $results['service'] = $serviceResult;

            if ($requestedCheck === 'service') {
                echo $serviceResult ? '1' : '0';

                exit($serviceResult ? EXIT_SUCCESS : EXIT_SERVICE_ERROR);
            }
        }

        // For 'all' checks, return overall status
        if ($requestedCheck === 'all') {
            $overallStatus = $results['network'] && $results['auth'] && $results['service'];
            echo $overallStatus ? '1' : '0';

            // Exit with most specific error
            if (!$results['network']) {
                exit(EXIT_NETWORK_ERROR);
            }

            if (!$results['auth']) {
                exit(EXIT_AUTH_ERROR);
            }

            if (!$results['service']) {
                exit(EXIT_SERVICE_ERROR);
            }

            exit(EXIT_SUCCESS);
        }
    } catch (\Exception $e) {
        error_log('AbraFlexi Network Check Error: '.$e->getMessage());
        echo '0';

        exit(EXIT_SERVICE_ERROR);
    }
}

/**
 * Test basic network connectivity to AbraFlexi server.
 */
function testNetworkConnectivity(string $baseUrl): bool
{
    try {
        $parsedUrl = parse_url($baseUrl);
        $host = $parsedUrl['host'] ?? '';
        $port = $parsedUrl['port'] ?? 80;

        if (empty($host)) {
            return false;
        }

        // Test TCP connection
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Test authentication with AbraFlexi API.
 */
function testAuthentication(string $baseUrl): bool
{
    try {
        $statusUrl = rtrim($baseUrl, '/').'/status.json';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Basic '.base64_encode(
                        \Ease\Shared::cfg('ABRAFLEXI_LOGIN').':'.
                        \Ease\Shared::cfg('ABRAFLEXI_PASSWORD'),
                    ),
                    'Accept: application/json',
                    'User-Agent: AbraFlexi-Zabbix-NetworkCheck/1.0',
                ],
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($statusUrl, false, $context);

        // Check if we got a proper response (not 401/403)
        if ($response === false) {
            // Check if it's an auth error specifically
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (str_contains($header, '401') || str_contains($header, '403')) {
                        return false;
                    }
                }
            }

            return false;
        }

        // Verify we got valid JSON
        $data = json_decode($response, true);

        return $data !== null;
    } catch (\Exception $e) {
        return false;
    }
}

/**
 * Test AbraFlexi service health.
 */
function testServiceHealth(string $baseUrl): bool
{
    try {
        $statusUrl = rtrim($baseUrl, '/').'/status.json';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Basic '.base64_encode(
                        \Ease\Shared::cfg('ABRAFLEXI_LOGIN').':'.
                        \Ease\Shared::cfg('ABRAFLEXI_PASSWORD'),
                    ),
                    'Accept: application/json',
                    'User-Agent: AbraFlexi-Zabbix-ServiceCheck/1.0',
                ],
                'timeout' => 10,
            ],
        ]);

        $response = @file_get_contents($statusUrl, false, $context);

        if ($response === false) {
            return false;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['status'])) {
            return false;
        }

        $status = $data['status'];

        // Check critical service indicators
        $appServerRunning = ($status['appServerRunning'] ?? 'false') === 'true';
        $hasVersion = !empty($status['version'] ?? '');
        $hasUuid = !empty($status['uuid'] ?? '');

        return $appServerRunning && $hasVersion && $hasUuid;
    } catch (\Exception $e) {
        return false;
    }
}

// Get check type from command line args
$checkType = $argv[1] ?? 'all';
performNetworkCheck($checkType);
