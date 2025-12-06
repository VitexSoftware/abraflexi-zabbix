<?php

declare(strict_types=1);

namespace Test\AbraFlexi\Zabbix;

use AbraFlexi\Zabbix\Feeder;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for AbraFlexi Zabbix monitoring
 */
class IntegrationTest extends TestCase
{
    public function testFullWorkflow(): void
    {
        // Test complete workflow: system status -> network check -> company LLD
        $feeder = new Feeder();
        
        // 1. Get system status
        ob_start();
        $feeder->getCachedSystemStatus('', true, false);
        $status = ob_get_clean();
        $statusData = json_decode($status, true);
        $this->assertIsArray($statusData);
        
        // 2. Perform network check
        ob_start();
        $feeder->performNetworkCheck('all', true, false);
        $networkResult = ob_get_clean();
        $networkData = json_decode($networkResult, true);
        $this->assertArrayHasKey('summary', $networkData);
        
        // 3. Get company LLD
        ob_start();
        $feeder->getCompanyLLD(true, false);
        $lld = ob_get_clean();
        $lldData = json_decode($lld, true);
        $this->assertIsArray($lldData);
    }

    public function testAllCommandLineFlags(): void
    {
        $feeder = new Feeder();
        
        $commands = [
            ['getCachedSystemStatus', ['', false, false]],
            ['getCachedSystemStatus', ['', true, false]],
            ['getCachedSystemStatus', ['', false, true]],
            ['getCachedSystemStatus', ['', true, true]],
            ['performNetworkCheck', ['all', false, false]],
            ['performNetworkCheck', ['all', true, false]],
            ['getCompanyLLD', [false, false]],
            ['getCompanyLLD', [true, false]],
            ['getCompanyLLD', [false, true]],
        ];

        foreach ($commands as [$method, $args]) {
            ob_start();
            $feeder->$method(...$args);
            $result = ob_get_clean();
            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        }
    }

    public function testErrorHandling(): void
    {
        // Test with invalid URL to check error handling
        $originalUrl = $_ENV['ABRAFLEXI_URL'] ?? '';
        $_ENV['ABRAFLEXI_URL'] = 'https://invalid.example.com';
        
        $feeder = new Feeder();
        ob_start();
        $feeder->performNetworkCheck('all', true, false);
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // Should contain error information in debug mode
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        
        // Restore valid URL
        $_ENV['ABRAFLEXI_URL'] = $originalUrl ?: 'https://demo.flexibee.eu:5434';
    }
}