<?php

declare(strict_types=1);

namespace Test\AbraFlexi\Zabbix;

use AbraFlexi\Zabbix\Feeder;
use PHPUnit\Framework\TestCase;

/**
 * Test class for AbraFlexi Zabbix Feeder
 */
class FeederTest extends TestCase
{
    private Feeder $feeder;

    protected function setUp(): void
    {
        $this->feeder = new Feeder();
    }

    public function testFeederInstantiation(): void
    {
        $this->assertInstanceOf(Feeder::class, $this->feeder);
    }

    public function testGetCachedSystemStatus(): void
    {
        ob_start();
        $this->feeder->getCachedSystemStatus();
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // Test that it returns valid JSON
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
    }

    public function testGetCachedSystemStatusWithDebug(): void
    {
        ob_start();
        $this->feeder->getCachedSystemStatus('', true, false);
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // With debug, JSON should be pretty printed (contains newlines)
        $this->assertStringContainsString("\n", $result);
    }

    public function testGetCachedSystemStatusWithColor(): void
    {
        ob_start();
        $this->feeder->getCachedSystemStatus('', true, true); // Both debug and color need to be true
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // Should contain ANSI color codes when both debug and color modes are enabled
        $this->assertMatchesRegularExpression('/\e\[[0-9;]*m/', $result);
    }

    public function testPerformNetworkCheck(): void
    {
        ob_start();
        $this->feeder->performNetworkCheck();
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testPerformNetworkCheckWithDebug(): void
    {
        ob_start();
        $this->feeder->performNetworkCheck('all', true, false);
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // Should contain network check information
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('details', $decoded);
        $this->assertArrayHasKey('overall_status', $decoded['summary']);
    }

    public function testGetCompanyLLD(): void
    {
        ob_start();
        $this->feeder->getCompanyLLD();
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // Test that it returns valid JSON array (Zabbix LLD format)
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
        
        // Should contain company items with required LLD macros
        if (count($decoded) > 0) {
            $this->assertArrayHasKey('{#COMPANY_CODE}', $decoded[0]);
            $this->assertArrayHasKey('{#COMPANY_NAME}', $decoded[0]);
        }
    }

    public function testGetCompanyLLDWithDebug(): void
    {
        ob_start();
        $this->feeder->getCompanyLLD(true, false);
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // With debug, JSON should be pretty printed
        $this->assertStringContainsString("\n", $result);
    }

    public function testColorizeJsonThroughOutput(): void
    {
        ob_start();
        $this->feeder->getCachedSystemStatus('', true, true); // Both debug and color needed
        $result = ob_get_clean();
        
        // Should contain ANSI color codes when both debug and color modes are enabled
        $this->assertStringContainsString("\e[", $result);
    }

    public function testJsonPrettyPrint(): void
    {
        // Test that debug mode produces pretty printed JSON
        ob_start();
        $this->feeder->getCachedSystemStatus('', true, false);
        $result = ob_get_clean();
        
        $this->assertStringContainsString("\n", $result);
    }

    public function testArgumentParsing(): void
    {
        // Test that the feeder correctly handles command line arguments
        ob_start();
        $this->feeder->getCachedSystemStatus('', true, true);
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // Should contain both debug (pretty print) and color formatting
        $this->assertStringContainsString("\n", $result);
        $this->assertMatchesRegularExpression('/\e\[[0-9;]*m/', $result);
    }

    public function testCaching(): void
    {
        // Clear any existing cache
        $cacheDir = sys_get_temp_dir() . '/abraflexi-zabbix-cache';
        $cacheFile = $cacheDir . '/status_cache.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        // First call should create cache
        ob_start();
        $this->feeder->getCachedSystemStatus();
        $result1 = ob_get_clean();
        
        // Cache file should now exist
        $this->assertFileExists($cacheFile);
        $cacheTime1 = filemtime($cacheFile);
        
        // Small delay to ensure different timestamps if cache was recreated
        usleep(10000); // 10ms
        
        // Second call should use cache (not recreate it)
        ob_start();
        $this->feeder->getCachedSystemStatus();
        $result2 = ob_get_clean();
        
        $cacheTime2 = filemtime($cacheFile);
        
        // Results should be identical and cache file should not be newer
        $this->assertEquals($result1, $result2);
        $this->assertEquals($cacheTime1, $cacheTime2, 'Cache file was recreated when it should have been reused');
    }

    public function testResponseTimeLogging(): void
    {
        ob_start();
        $this->feeder->getCachedSystemStatus('', true, false);
        $result = ob_get_clean();
        
        $decoded = json_decode($result, true);
        
        $this->assertArrayHasKey('responseTime', $decoded);
        $this->assertIsNumeric($decoded['responseTime']);
        $this->assertGreaterThan(0, $decoded['responseTime']);
    }
}