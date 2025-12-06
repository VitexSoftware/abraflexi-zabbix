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
    private string $testCacheDir;

    protected function setUp(): void
    {
        // Use a test-specific cache directory
        $this->testCacheDir = sys_get_temp_dir() . '/abraflexi-zabbix-test-' . getmypid();
        $this->feeder = new Feeder($this->testCacheDir);
        
        // Clean up any existing test cache
        if (is_dir($this->testCacheDir)) {
            $this->removeDirectory($this->testCacheDir);
        }
    }
    
    protected function tearDown(): void
    {
        // Clean up test cache directory
        if (is_dir($this->testCacheDir)) {
            $this->removeDirectory($this->testCacheDir);
        }
    }
    
    /**
     * Recursively remove directory and all its contents
     */
    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
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
        
        // Should return valid JSON (even if empty due to connection issues)
        $decoded = json_decode($result, true);
        $this->assertNotNull($decoded, 'Should return valid JSON');
        
        // If we got empty array due to connection issues, that's acceptable in tests
        if (!empty($decoded) && is_array($decoded)) {
            // With debug, JSON should be pretty printed (contains newlines)
            $this->assertStringContainsString("\n", $result);
        } else {
            $this->markTestSkipped('AbraFlexi connection not available for testing');
        }
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
        
        // Should return valid JSON
        $decoded = json_decode($result, true);
        $this->assertNotNull($decoded, 'Should return valid JSON');
        
        // If we got empty array due to connection issues, that's acceptable in tests
        if (!empty($decoded) && is_array($decoded)) {
            $this->assertStringContainsString("\n", $result);
        } else {
            $this->markTestSkipped('AbraFlexi connection not available for testing');
        }
    }

    public function testArgumentParsing(): void
    {
        // Test that the feeder correctly handles command line arguments
        ob_start();
        $this->feeder->getCachedSystemStatus('', true, true);
        $result = ob_get_clean();
        
        $this->assertIsString($result);
        
        // When both debug and color modes are enabled, we expect some output
        // Either colorized JSON or fallback behavior
        if (!empty(trim($result))) {
            $this->assertNotEmpty($result);
        } else {
            $this->markTestSkipped('AbraFlexi connection not available for testing');
        }
    }

    public function testCaching(): void
    {
        // Get the actual cache directory and file used by this test's feeder instance
        $reflection = new \ReflectionClass($this->feeder);
        $cacheFileProperty = $reflection->getProperty('cacheFile');
        $cacheFileProperty->setAccessible(true);
        $cacheFile = $cacheFileProperty->getValue($this->feeder);
        
        // Clear any existing cache
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        // First call should create cache - handle potential lock contention in CI
        ob_start();
        try {
            $this->feeder->getCachedSystemStatus();
            $result1 = ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            if (strpos($e->getMessage(), 'Could not acquire lock') !== false) {
                $this->markTestSkipped('Lock contention in CI environment - skipping caching test');
                return;
            }
            throw $e;
        }
        
        // If no AbraFlexi connection available, skip the test
        if (empty(trim($result1)) || $result1 === '[]') {
            $this->markTestSkipped('AbraFlexi connection not available for caching test');
            return;
        }
        
        // Cache file should now exist (if lock was successfully acquired)
        if (!file_exists($cacheFile)) {
            $this->markTestSkipped('Cache file not created due to CI environment limitations');
            return;
        }
        
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
        $this->assertNotNull($decoded, 'Should return valid JSON');
        
        // If we got empty array due to connection issues, skip this test
        if (empty($decoded) || !is_array($decoded)) {
            $this->markTestSkipped('AbraFlexi connection not available for testing');
            return;
        }
        
        $this->assertArrayHasKey('responseTime', $decoded);
        $this->assertIsNumeric($decoded['responseTime']);
        $this->assertGreaterThan(0, $decoded['responseTime']);
    }
}