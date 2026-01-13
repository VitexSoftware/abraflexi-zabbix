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

/**
 * AbraFlexi Zabbix data feeder with intelligent caching.
 */
class Feeder
{
    private const CACHE_TTL = 30; // Cache for 30 seconds

    /**
     * Ansi Codes (same as Ease Logger ToConsole).
     */
    protected static array $ansiCodes = [
        'off' => 0,
        'bold' => 1,
        'italic' => 3,
        'underline' => 4,
        'blink' => 5,
        'inverse' => 7,
        'hidden' => 8,
        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'magenta' => 35,
        'cyan' => 36,
        'white' => 37,
        'black_bg' => 40,
        'red_bg' => 41,
        'green_bg' => 42,
        'yellow_bg' => 43,
        'blue_bg' => 44,
        'magenta_bg' => 45,
        'cyan_bg' => 46,
        'white_bg' => 47,
    ];
    private string $cacheDir;
    private string $cacheFile;
    private string $lockFile;
    private bool $testMode;

    public function __construct(?string $cacheDir = null)
    {
        if ($cacheDir !== null) {
            $this->cacheDir = $cacheDir;
        } else {
            // Try system cache directory first, fallback to user temp if not writable
            $systemCacheDir = '/var/cache/abraflexi-zabbix';

            if (is_writable(\dirname($systemCacheDir)) && (is_dir($systemCacheDir) || mkdir($systemCacheDir, 0o755, true))) {
                $this->cacheDir = $systemCacheDir;
            } else {
                // Fallback to user temp directory for development
                $this->cacheDir = sys_get_temp_dir().'/abraflexi-zabbix-cache-'.posix_getuid();
            }
        }

        $this->cacheFile = $this->cacheDir.'/status_cache.json';
        $this->lockFile = $this->cacheDir.'/status_cache.lock';
        $this->testMode = \defined('TEST_ENV') && TEST_ENV === true;

        // Ensure cache directory exists
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0o755, true);
        }
    }

    /**
     * Get cached system status with optional metric filtering.
     */
    public function getCachedSystemStatus(string $metric = '', bool $debugMode = false, bool $colorMode = false): void
    {
        try {
            $cachedData = $this->getCachedData();

            if ($cachedData === null) {
                // Cache miss - fetch fresh data with file locking to prevent concurrent requests
                $cachedData = $this->fetchAndCacheData();
            }

            // Return requested metric or all data
            if (!empty($metric)) {
                if (!\array_key_exists($metric, $cachedData)) {
                    throw new \Exception("Metric '{$metric}' not found in cached data");
                }

                $value = $cachedData[$metric];

                // Convert specific values to numeric format for Zabbix
                switch ($metric) {
                    case 'systemLoad':
                        echo (float) $value;

                        break;
                    case 'memoryUsed':
                    case 'memoryHeap':
                    case 'bytesRead':
                    case 'bytesWritten':
                    case 'totalGcTime':
                    case 'responseTime':
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
                $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

                if ($debugMode) {
                    $jsonFlags |= \JSON_PRETTY_PRINT;
                }

                $jsonOutput = json_encode($cachedData, $jsonFlags);

                if ($colorMode && $debugMode) {
                    echo $this->colorizeJson($jsonOutput)."\n";
                } else {
                    echo $jsonOutput."\n";
                }
            }

            // Only exit if not in test mode
            if (!$this->testMode) {
                exit(0);
            }
        } catch (\Exception $e) {
            // Log error and exit with appropriate default
            if (!$this->testMode) {
                error_log('AbraFlexi Cached Status Error: '.$e->getMessage());
            }

            if (!empty($metric)) {
                echo self::getDefaultValue($metric)."\n";
            } else {
                $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

                if ($debugMode) {
                    $jsonFlags |= \JSON_PRETTY_PRINT;
                }

                $jsonOutput = json_encode([], $jsonFlags);

                if ($colorMode && $debugMode) {
                    echo $this->colorizeJson($jsonOutput)."\n";
                } else {
                    echo $jsonOutput."\n";
                }
            }

            // Only exit if not in test mode
            if (!$this->testMode) {
                exit(1);
            }
        }
    }

    /**
     * Set Ansi Color (same method as Ease Logger ToConsole).
     *
     * @param string $str   string to colorize
     * @param string $color color name
     *
     * @return string
     */
    public static function setColor($str, $color)
    {
        $colorAttrs = explode('+', $color);
        $ansiStr = '';

        foreach ($colorAttrs as $attr) {
            $ansiStr .= "\033[".self::$ansiCodes[$attr].'m';
        }

        return $ansiStr.($str."\033[".self::$ansiCodes['off'].'m');
    }

    /**
     * Generate Zabbix Low Level Discovery JSON for AbraFlexi companies.
     */
    public function getCompanyLLD(bool $debugMode = false, bool $colorMode = false): void
    {
        try {
            $checker = new \AbraFlexi\Company();
            $listing = $checker->getAllFromAbraFlexi();

            if (!\is_array($listing)) {
                throw new \Exception('Failed to retrieve company list from AbraFlexi API');
            }

            // Transform data to Zabbix LLD format
            $lldData = [];

            foreach ($listing as $company) {
                if (!isset($company['dbNazev']) || !isset($company['nazev'])) {
                    continue; // Skip invalid entries
                }

                $lldData[] = [
                    '{#COMPANY_CODE}' => $company['dbNazev'],
                    '{#COMPANY_NAME}' => $company['nazev'],
                    '{#COMPANY_DB}' => $company['dbNazev'],
                    '{#COMPANY_ID}' => (string) ($company['id'] ?? ''),
                    '{#COMPANY_STATE}' => $company['stavEnum'] ?? '',
                    '{#COMPANY_SHOW}' => $company['show'] ? '1' : '0',
                    '{#COMPANY_WATCHING}' => $company['watchingChanges'] ? '1' : '0',
                ];
            }

            // Output Zabbix LLD JSON
            $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

            if ($debugMode) {
                $jsonFlags |= \JSON_PRETTY_PRINT;
            }

            $jsonOutput = json_encode($lldData, $jsonFlags);

            if ($colorMode && $debugMode) {
                echo $this->colorizeJson($jsonOutput)."\n";
            } else {
                echo $jsonOutput."\n";
            }

            $this->exitUnlessTest(0);
        } catch (\Exception $e) {
            // Log error and return empty JSON array for Zabbix
            if (!$this->testMode) {
                error_log('AbraFlexi Zabbix LLD Error: '.$e->getMessage());
            }

            $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

            if ($debugMode) {
                $jsonFlags |= \JSON_PRETTY_PRINT;
            }

            $jsonOutput = json_encode([], $jsonFlags);

            if ($colorMode && $debugMode) {
                echo $this->colorizeJson($jsonOutput)."\n";
            } else {
                echo $jsonOutput."\n";
            }

            $this->exitUnlessTest(1);
        }
    }

    /**
     * Perform comprehensive network and service check for AbraFlexi.
     */
    public function performNetworkCheck(string $checkType = 'all', bool $debugMode = false, bool $colorMode = false): void
    {
        try {
            $baseUrl = \Ease\Shared::cfg('ABRAFLEXI_URL');

            if (empty($baseUrl)) {
                if ($debugMode) {
                    $errorInfo = ['error' => 'Configuration error: ABRAFLEXI_URL not set'];
                    $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT;
                    $jsonOutput = json_encode($errorInfo, $jsonFlags);

                    if ($colorMode) {
                        echo $this->colorizeJson($jsonOutput)."\n";
                    } else {
                        echo $jsonOutput."\n";
                    }
                } else {
                    echo "0\n"; // Configuration error
                }

                $this->exitUnlessTest(3); // EXIT_SERVICE_ERROR
            }

            $results = [];
            $detailedResults = [];

            // Test 1: Basic Network Connectivity
            if ($checkType === 'network' || $checkType === 'all') {
                $networkResult = self::testNetworkConnectivity($baseUrl);
                $results['network'] = $networkResult;
                $detailedResults['network'] = [
                    'test' => 'Network Connectivity',
                    'url' => $baseUrl,
                    'result' => $networkResult ? 'PASS' : 'FAIL',
                    'status' => $networkResult,
                ];

                if ($checkType === 'network') {
                    if ($debugMode) {
                        $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT;
                        $jsonOutput = json_encode($detailedResults['network'], $jsonFlags);

                        if ($colorMode) {
                            echo $this->colorizeJson($jsonOutput)."\n";
                        } else {
                            echo $jsonOutput."\n";
                        }
                    } else {
                        echo ($networkResult ? '1' : '0')."\n";
                    }

                    $this->exitUnlessTest($networkResult ? 0 : 1);
                }
            }

            // Test 2: Authentication
            if ($checkType === 'auth' || $checkType === 'all') {
                $authResult = self::testAuthentication($baseUrl);
                $results['auth'] = $authResult;
                $detailedResults['auth'] = [
                    'test' => 'Authentication',
                    'url' => $baseUrl,
                    'login' => \Ease\Shared::cfg('ABRAFLEXI_LOGIN'),
                    'result' => $authResult ? 'PASS' : 'FAIL',
                    'status' => $authResult,
                ];

                if ($checkType === 'auth') {
                    if ($debugMode) {
                        $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT;
                        $jsonOutput = json_encode($detailedResults['auth'], $jsonFlags);

                        if ($colorMode) {
                            echo $this->colorizeJson($jsonOutput)."\n";
                        } else {
                            echo $jsonOutput."\n";
                        }
                    } else {
                        echo ($authResult ? '1' : '0')."\n";
                    }

                    $this->exitUnlessTest($authResult ? 0 : 2);
                }
            }

            // Test 3: Service Health
            if ($checkType === 'service' || $checkType === 'all') {
                $serviceResult = self::testServiceHealth($baseUrl);
                $results['service'] = $serviceResult;
                $detailedResults['service'] = [
                    'test' => 'Service Health',
                    'url' => $baseUrl,
                    'result' => $serviceResult ? 'PASS' : 'FAIL',
                    'status' => $serviceResult,
                ];

                if ($checkType === 'service') {
                    if ($debugMode) {
                        $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT;
                        $jsonOutput = json_encode($detailedResults['service'], $jsonFlags);

                        if ($colorMode) {
                            echo $this->colorizeJson($jsonOutput)."\n";
                        } else {
                            echo $jsonOutput."\n";
                        }
                    } else {
                        echo ($serviceResult ? '1' : '0')."\n";
                    }

                    $this->exitUnlessTest($serviceResult ? 0 : 3);
                }
            }

            // For 'all' checks, return overall status
            if ($checkType === 'all') {
                $overallStatus = $results['network'] && $results['auth'] && $results['service'];

                if ($debugMode) {
                    $summaryResults = [
                        'summary' => [
                            'overall' => $overallStatus ? 'PASS' : 'FAIL',
                            'overall_status' => $overallStatus,
                        ],
                        'details' => $detailedResults,
                    ];
                    $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT;
                    $jsonOutput = json_encode($summaryResults, $jsonFlags);

                    if ($colorMode) {
                        echo $this->colorizeJson($jsonOutput)."\n";
                    } else {
                        echo $jsonOutput."\n";
                    }
                } else {
                    echo ($overallStatus ? '1' : '0')."\n";
                }

                // Exit with most specific error
                if (!$results['network']) {
                    $this->exitUnlessTest(1);

                    return;
                }

                if (!$results['auth']) {
                    $this->exitUnlessTest(2);

                    return;
                }

                if (!$results['service']) {
                    $this->exitUnlessTest(3);

                    return;
                }

                $this->exitUnlessTest(0);
            }
        } catch (\Exception $e) {
            if (!$this->testMode) {
                error_log('AbraFlexi Network Check Error: '.$e->getMessage());
            }

            if ($debugMode) {
                $errorInfo = ['error' => 'Network Check Error: '.$e->getMessage()];
                $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT;
                $jsonOutput = json_encode($errorInfo, $jsonFlags);

                if ($colorMode) {
                    echo $this->colorizeJson($jsonOutput)."\n";
                } else {
                    echo $jsonOutput."\n";
                }
            } else {
                echo "0\n";
            }

            $this->exitUnlessTest(3);
        }
    }

    /**
     * Get direct system status without caching.
     */
    public function getDirectSystemStatus(string $metric = '', bool $debugMode = false, bool $colorMode = false): void
    {
        try {
            // Fetch fresh data directly
            $startTime = microtime(true);
            $checker = new \AbraFlexi\Status();
            $status = $checker->getData();
            $endTime = microtime(true);

            // Calculate response time in milliseconds
            $responseTime = round(($endTime - $startTime) * 1000);

            if (!\is_array($status)) {
                throw new \Exception('Failed to retrieve status data from AbraFlexi API');
            }

            // Add response time and configuration information
            $status['responseTime'] = $responseTime;
            $status['configUrl'] = \Ease\Shared::cfg('ABRAFLEXI_URL');
            $status['configLogin'] = \Ease\Shared::cfg('ABRAFLEXI_LOGIN');

            // Return requested metric or all data
            if (!empty($metric)) {
                if (!\array_key_exists($metric, $status)) {
                    throw new \Exception("Metric '{$metric}' not found in status data");
                }

                $value = $status[$metric];

                // Convert specific values to numeric format for Zabbix
                switch ($metric) {
                    case 'systemLoad':
                        echo (float) $value;

                        break;
                    case 'memoryUsed':
                    case 'memoryHeap':
                    case 'bytesRead':
                    case 'bytesWritten':
                    case 'totalGcTime':
                    case 'responseTime':
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
                $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

                if ($debugMode) {
                    $jsonFlags |= \JSON_PRETTY_PRINT;
                }

                $jsonOutput = json_encode($status, $jsonFlags);

                if ($colorMode && $debugMode) {
                    echo $this->colorizeJson($jsonOutput)."\n";
                } else {
                    echo $jsonOutput."\n";
                }
            }

            exit(0);
        } catch (\Exception $e) {
            // Log error and exit with appropriate default
            if (!$this->testMode) {
                error_log('AbraFlexi System Status Error: '.$e->getMessage());
            }

            if (!empty($metric)) {
                echo self::getDefaultValue($metric)."\n";
            } else {
                $jsonFlags = \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;

                if ($debugMode) {
                    $jsonFlags |= \JSON_PRETTY_PRINT;
                }

                $jsonOutput = json_encode([], $jsonFlags);

                if ($colorMode && $debugMode) {
                    echo $this->colorizeJson($jsonOutput)."\n";
                } else {
                    echo $jsonOutput."\n";
                }
            }

            exit(1);
        }
    }

    /**
     * Parse command line arguments and execute cached status retrieval.
     */
    public static function handleCommandLine(string $envf): void
    {
        // Parse command line arguments
        $options = getopt('m::e::d::c::', ['metric::', 'env::', 'debug::', 'color::']);

        // Get the path to the .env file
        $envfile = $options['env'] ?? $envf;
        \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

        // Get metric from command line if provided
        $requestedMetric = $options['metric'] ?? '';

        // Check for debug and color flags
        $debugMode = isset($options['debug']) || isset($options['d']);
        $colorMode = isset($options['color']) || isset($options['c']);

        // Find first non-flag argument as metric if not specified via -m
        if (empty($requestedMetric)) {
            global $argv;

            foreach ($argv as $index => $arg) {
                if ($index === 0) {
                    continue;
                }

                // Skip script name

                // Skip flags and their values
                if (str_starts_with($arg, '-')) {
                    continue;
                }

                // Check if previous argument was a flag that takes a value
                $prevArg = $argv[$index - 1] ?? '';

                if (\in_array($prevArg, ['-m', '--metric', '-e', '--env'], true)) {
                    continue;
                }

                $requestedMetric = $arg;

                break;
            }
        }

        $feeder = new self();
        $feeder->getCachedSystemStatus($requestedMetric, $debugMode, $colorMode);
    }

    /**
     * Parse command line arguments and execute direct status retrieval.
     */
    public static function handleDirectCommandLine(): void
    {
        // Parse command line arguments
        $options = getopt('m::e::d::c::', ['metric::', 'env::', 'debug::', 'color::']);

        // Get the path to the .env file
        $envfile = $options['env'] ?? '../.env';
        \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

        // Get metric from command line if provided
        $requestedMetric = $options['metric'] ?? '';

        // Check for debug and color flags
        $debugMode = isset($options['debug']) || isset($options['d']);
        $colorMode = isset($options['color']) || isset($options['c']);

        // Find first non-flag argument as metric if not specified via -m
        if (empty($requestedMetric)) {
            global $argv;

            foreach ($argv as $index => $arg) {
                if ($index === 0) {
                    continue;
                }

                // Skip script name

                // Skip flags and their values
                if (str_starts_with($arg, '-')) {
                    continue;
                }

                // Check if previous argument was a flag that takes a value
                $prevArg = $argv[$index - 1] ?? '';

                if (\in_array($prevArg, ['-m', '--metric', '-e', '--env'], true)) {
                    continue;
                }

                $requestedMetric = $arg;

                break;
            }
        }

        $feeder = new self();
        $feeder->getDirectSystemStatus($requestedMetric, $debugMode, $colorMode);
    }

    /**
     * Parse command line arguments and execute company LLD.
     */
    public static function handleCompanyLLD(): void
    {
        // Parse command line arguments
        $options = getopt('m::e::d::c::', ['mode::', 'env::', 'debug::', 'color::']);

        // Get the path to the .env file
        $envfile = $options['env'] ?? '../.env';
        \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

        // Check for debug and color flags
        $debugMode = isset($options['debug']) || isset($options['d']);
        $colorMode = isset($options['color']) || isset($options['c']);

        $feeder = new self();
        $feeder->getCompanyLLD($debugMode, $colorMode);
    }

    /**
     * Parse command line arguments and execute network check.
     */
    public static function handleNetworkCheck(): void
    {
        // Parse command line arguments
        $options = getopt('t::e::d::c::', ['type::', 'env::', 'debug::', 'color::']);

        // Get the path to the .env file
        $envfile = $options['env'] ?? '../.env';
        \Ease\Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD'], $envfile);

        // Get check type from command line if provided
        $requestedCheck = $options['type'] ?? '';

        // Check for debug and color flags
        $debugMode = isset($options['debug']) || isset($options['d']);
        $colorMode = isset($options['color']) || isset($options['c']);

        // Find first non-flag argument as check type if not specified via -t
        if (empty($requestedCheck)) {
            global $argv;

            foreach ($argv as $index => $arg) {
                if ($index === 0) {
                    continue;
                }

                // Skip script name

                // Skip flags and their values
                if (str_starts_with($arg, '-')) {
                    continue;
                }

                // Check if previous argument was a flag that takes a value
                $prevArg = $argv[$index - 1] ?? '';

                if (\in_array($prevArg, ['-t', '--type', '-e', '--env'], true)) {
                    continue;
                }

                $requestedCheck = $arg;

                break;
            }
        }

        $feeder = new self();
        $feeder->performNetworkCheck($requestedCheck ?: 'all', $debugMode, $colorMode);
    }

    /**
     * Exit with the given code unless in test mode.
     */
    private function exitUnlessTest(int $exitCode): void
    {
        if (!$this->testMode) {
            exit($exitCode);
        }
    }

    /**
     * Get cached data if valid.
     */
    private function getCachedData(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $cacheTime = filemtime($this->cacheFile);
        $currentTime = time();

        // Check if cache is still valid
        if (($currentTime - $cacheTime) > self::CACHE_TTL) {
            return null;
        }

        $cachedContent = file_get_contents($this->cacheFile);

        if ($cachedContent === false) {
            return null;
        }

        $data = json_decode($cachedContent, true);

        return \is_array($data) ? $data : null;
    }

    /**
     * Fetch fresh data and cache it with file locking.
     */
    private function fetchAndCacheData(): array
    {
        // Ensure lock directory exists in case it was removed after construction (e.g. in tests)
        $lockDir = \dirname($this->lockFile);

        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0o755, true);
        }

        // Acquire lock to prevent multiple concurrent requests
        $lockHandle = fopen($this->lockFile, 'cb');

        if (!$lockHandle || !flock($lockHandle, \LOCK_EX | \LOCK_NB)) {
            // Another process is fetching - wait a bit and try cache again
            if ($lockHandle) {
                fclose($lockHandle);
            }

            usleep(100000); // Wait 100ms

            $cachedData = $this->getCachedData();

            if ($cachedData !== null) {
                return $cachedData;
            }

            // Still no cache - throw error to avoid infinite waiting
            if (!$this->testMode) {
                error_log('AbraFlexi Zabbix: Could not acquire lock and no cached data available');
            }

            throw new \Exception('Could not acquire lock and no cached data available');
        }

        try {
            // Double-check cache while we have the lock
            $cachedData = $this->getCachedData();

            if ($cachedData !== null) {
                return $cachedData;
            }

            // Fetch fresh data with response time measurement
            $startTime = microtime(true);
            $checker = new \AbraFlexi\Status();
            $status = $checker->getData();
            $endTime = microtime(true);

            // Calculate response time in milliseconds
            $responseTime = round(($endTime - $startTime) * 1000);

            if (!\is_array($status)) {
                // Set response time to 0 for failed requests
                $status = ['responseTime' => 0];

                throw new \Exception('Failed to retrieve status data from AbraFlexi API');
            }

            // Add response time to status data
            $status['responseTime'] = $responseTime;

            // Add configuration information as additional metrics
            $status['configUrl'] = \Ease\Shared::cfg('ABRAFLEXI_URL');
            $status['configLogin'] = \Ease\Shared::cfg('ABRAFLEXI_LOGIN');

            // Cache the data
            $cacheContent = json_encode($status, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            file_put_contents($this->cacheFile, $cacheContent, \LOCK_EX);

            // Set cache file timestamp
            touch($this->cacheFile);

            return $status;
        } finally {
            // Always release lock
            flock($lockHandle, \LOCK_UN);
            fclose($lockHandle);
            @unlink($this->lockFile);
        }
    }

    /**
     * Add color syntax highlighting to JSON string.
     */
    private function colorizeJson(string $json): string
    {
        // Check if colors are supported or forced
        if (!self::supportsColor()) {
            return $json;
        }

        // Apply color coding using the same method as Ease Logger ToConsole
        $json = preg_replace_callback('/("([^"\\\\]|\\\\.)*")(\s*:)/', static function ($matches) {
            return self::setColor($matches[1], 'blue').$matches[3]; // Keys in blue
        }, $json);

        $json = preg_replace_callback('/:\s*("([^"\\\\]|\\\\.)*")/', static function ($matches) {
            return ': '.self::setColor($matches[1], 'green'); // String values in green
        }, $json);

        $json = preg_replace_callback('/:\s*(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/', static function ($matches) {
            return ': '.self::setColor($matches[1], 'yellow'); // Numbers in yellow
        }, $json);

        $json = preg_replace_callback('/:\s*(true|false)/', static function ($matches) {
            return ': '.self::setColor($matches[1], 'red'); // Booleans in red
        }, $json);

        $json = preg_replace_callback('/:\s*(null)/', static function ($matches) {
            return ': '.self::setColor($matches[1], 'magenta'); // null in magenta
        }, $json);

        return preg_replace_callback('/([{}\\[\\]])/', static function ($matches) {
            return self::setColor($matches[1], 'white'); // Braces and brackets in white
        }, $json);
    }

    /**
     * Check if terminal supports colors.
     */
    private static function supportsColor(): bool
    {
        // Always return true when --color flag is explicitly used
        // The user explicitly requested colors, so we should provide them
        return true;
    }

    /**
     * Get default value for a metric when error occurs.
     */
    private static function getDefaultValue(string $metric): string
    {
        switch ($metric) {
            case 'systemLoad':
                return '0';
            case 'memoryUsed':
            case 'memoryHeap':
            case 'bytesRead':
            case 'bytesWritten':
            case 'totalGcTime':
            case 'responseTime':
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

    /**
     * Test basic network connectivity to AbraFlexi server.
     */
    private static function testNetworkConnectivity(string $baseUrl): bool
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
    private static function testAuthentication(string $baseUrl): bool
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
    private static function testServiceHealth(string $baseUrl): bool
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
}
