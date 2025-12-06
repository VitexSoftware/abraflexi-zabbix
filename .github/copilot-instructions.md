# AbraFlexi Zabbix - Copilot Instructions

Comprehensive monitoring solution for AbraFlexi server using Zabbix with Low Level Discovery, system metrics, and granular alerting.

## 🚀 Project Context

- **Technology**: PHP 8.4+, AbraFlexi API integration, Zabbix monitoring
- **Purpose**: Real-time monitoring and alerting for AbraFlexi accounting server
- **Architecture**: Command-line monitoring tools with intelligent caching system
- **Key Components**: System metrics collection, network health checks, company discovery, Zabbix LLD

## 📋 Code Standards & Requirements

### Language & Framework
- **PHP Version**: PHP 8.4 or later (strict requirement)
- **Coding Standard**: PSR-12 (mandatory for all PHP code)
- **Language**: All code comments, error messages, and documentation in English
- **Type Safety**: Always use type hints for parameters and return types

### Documentation Standards
```php
/**
 * Get cached system status with intelligent 30-second TTL caching.
 *
 * @param string $metric Specific metric to retrieve (empty for all metrics)
 * @param bool $debugMode Enable debug output with JSON formatting
 * @param bool $colorMode Enable colored terminal output
 * @throws Exception When metric not found or API error occurs
 */
public function getCachedSystemStatus(string $metric = '', bool $debugMode = false, bool $colorMode = false): void
```

### Testing Requirements
- **Framework**: PHPUnit for all tests
- **Coverage**: Create/update PHPUnit tests for every new/modified class
- **Validation**: Run `php -l` after every PHP file edit (mandatory)

## 🔧 Development Workflow

### File Structure & Paths
```bash
# Always run scripts from src/ directory during development
cd src/
php cached_system_status.php version

# Relative paths (../vendor/autoload.php, ../.env) are intentional
# They get resolved during Debian packaging via sed commands
```

### Code Quality Checklist
1. ✅ Use meaningful variable names that describe purpose
2. ✅ Define constants instead of magic numbers/strings  
3. ✅ Handle exceptions with meaningful error messages
4. ✅ Ensure security - no sensitive information exposure
5. ✅ Optimize for performance where applicable
6. ✅ Maintain compatibility with latest PHP/libraries
7. ✅ Follow maintainable coding practices

## 🌐 Internationalization
- **Library**: i18n for internationalization
- **Usage**: Always use `_()` functions for translatable strings
```php
error_log('AbraFlexi Zabbix LLD Error: ' . $e->getMessage());
$this->addStatusMessage(_('Network connectivity test failed'), 'error');
```

## 📄 Schema Compliance

### Zabbix Template Configuration
- **Location**: `zabbix/abraflexi-template.xml`
- **Format**: Zabbix 6.0+ XML export format
- **Items**: System metrics, network health, configuration visibility
- **Discovery**: Low Level Discovery for companies/databases

### Zabbix Agent Configuration
- **Agent 2**: `zabbix/abraflexi-agent2.conf` (recommended)
- **Agent 1**: `zabbix/abraflexi.conf` (legacy support)
- **UserParameters**: All monitoring metrics with proper error handling

**Example Metric Structure**:
```php
$status = [
    'version' => '2024.1.1',
    'systemLoad' => 1.23,
    'memoryUsed' => 1073741824,
    'appServerRunning' => 'true',
    'configUrl' => 'https://demo.flexibee.eu:5434',
    'configLogin' => 'winstrom'
];
```

## 🏗️ Key Classes & Components

### Main Classes
- `AbraFlexi\Zabbix\Feeder` - Unified monitoring data provider with caching
- `src/company_lld.php` - Company Low Level Discovery for Zabbix
- `src/cached_system_status.php` - System metrics with intelligent caching (30s TTL)
- `src/system_status.php` - Direct system metrics without caching
- `src/network_check.php` - Network connectivity and health testing

### Monitoring Components
- **System Metrics**: Performance, memory, users, sessions from `/status.json`
- **Network Health**: Connectivity, authentication, service availability
- **Company Discovery**: Automatic discovery of AbraFlexi companies/databases
- **Configuration Visibility**: Server URL and login credentials monitoring

### External Dependencies  
- **AbraFlexi API**: Communication with accounting system `/status.json` endpoint
- **EasePHP Framework**: Logging, configuration management, utilities
- **Zabbix Agent**: Monitoring data collection and alerting

## ⚡ Development Commands

```bash
# Code validation (mandatory after edits)
php -l filename.php

# Run tests
vendor/bin/phpunit tests/

# Test monitoring scripts
php src/cached_system_status.php --debug
php src/network_check.php all --debug

# Build packages
make clean && make deb
```

## 🎯 When Working on This Project

1. **New Features**: Always add corresponding tests
2. **Bug Fixes**: Ensure fix doesn't break existing functionality  
3. **Monitoring Changes**: Test all Zabbix items and triggers
4. **Documentation**: Update README.md with new metrics/capabilities
5. **Deployment**: Test relative path resolution works correctly
