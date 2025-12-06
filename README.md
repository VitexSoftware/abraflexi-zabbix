# AbraFlexi Zabbix Monitoring

<p align="center">
  <img src="project-logo.svg?raw=true" alt="AbraFlexi Zabbix Logo" width="200" height="200">
</p>

Comprehensive monitoring solution for AbraFlexi server using Zabbix with Low Level Discovery, system metrics, and granular alerting.

## Features

- **Low Level Discovery (LLD)** of AbraFlexi companies/databases
- **System metrics monitoring** from `/status.json` endpoint with intelligent caching including:
  - Application server status
  - Memory usage and heap monitoring
  - System load and performance metrics
  - User sessions and activity
  - Java runtime information
  - License information
- **Network connectivity monitoring** with granular error detection:
  - TCP connectivity tests
  - Authentication verification
  - Service health checks
- **Intelligent caching system** (30-second TTL) to minimize server load:
  - Single HTTP request serves all system metrics
  - 91% reduction in API requests
  - File-based caching with proper locking
- **Immediate alerting** with separate triggers for:
  - Network connectivity issues
  - Authentication failures
  - Service unavailability
  - Performance degradation
  - Company unavailability

## Installation

1. Install the package:
   ```bash
   # For Debian/Ubuntu
   sudo dpkg -i abraflexi-zabbix_*.deb
   
   # Or build from source
   make vendor
   ```

2. Configure environment variables:
   ```bash
   # Create .env file
   cat > /etc/abraflexi-zabbix/.env << EOF
   ABRAFLEXI_URL=https://your-abraflexi-server:5434
   ABRAFLEXI_LOGIN=your-username
   ABRAFLEXI_PASSWORD=your-password
   EOF
   ```

3. Install Zabbix agent configuration:
   ```bash
   # For Zabbix Agent 2 (recommended)
   sudo cp zabbix/abraflexi-agent2.conf /etc/zabbix/zabbix_agent2.d/
   sudo systemctl restart zabbix-agent2
   
   # For Zabbix Agent (legacy)
   sudo cp zabbix/abraflexi.conf /etc/zabbix/zabbix_agentd.d/
   sudo systemctl restart zabbix-agent
   ```

4. Import Zabbix template:
   - Open Zabbix web interface
   - Go to Data Collection → Templates
   - Click Import
   - Upload `zabbix/abraflexi-template.xml`
   - Apply template to your AbraFlexi server host

## Configuration

### Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `ABRAFLEXI_URL` | AbraFlexi server URL | `https://demo.flexibee.eu:5434` |
| `ABRAFLEXI_LOGIN` | API username | `winstrom` |
| `ABRAFLEXI_PASSWORD` | API password | `winstrom` |

### Zabbix Items

The template provides the following monitoring items:

#### Network Health
- `abraflexi.network.connectivity` - TCP connectivity test
- `abraflexi.network.authentication` - API authentication test  
- `abraflexi.network.service` - Service health check
- `abraflexi.network.overall` - Overall availability

#### System Metrics
- `abraflexi.system.version` - AbraFlexi version
- `abraflexi.system.appServerRunning` - Application server status
- `abraflexi.system.systemLoad` - System load average
- `abraflexi.system.memoryUsed` - Current memory usage
- `abraflexi.system.memoryHeap` - JVM heap size
- `abraflexi.system.loggedUser` - Number of logged users
- `abraflexi.system.sessions` - Active sessions count
- `abraflexi.system.bytesRead` - Total bytes read
- `abraflexi.system.totalGcTime` - Garbage collection time
- `abraflexi.system.javaVersion` - Java runtime version

#### Configuration Information  
- `abraflexi.config.url` - Monitored AbraFlexi server URL
- `abraflexi.config.login` - API username being used for monitoring

#### Company Discovery
- `abraflexi.company.lld` - Discovers all available companies
- `abraflexi.company.available[{#COMPANY_CODE}]` - Per-company availability

### Triggers

#### Critical Alerts (Disaster/High Priority)
- **Network connectivity failure** - Cannot reach AbraFlexi server
- **Authentication failure** - Invalid credentials or permissions
- **Service health failure** - AbraFlexi service issues
- **Application server not running** - App server down
- **Company unavailable** - Specific company/database inaccessible

#### Performance Warnings
- **High system load** - Load average > 5.0
- **High memory usage** - Memory usage > 90% of heap
- **High user count** - More than 100 concurrent users

## Usage

### Testing Individual Components

```bash
# Test company discovery
php src/company_lld.php

# Test system status (direct)
php src/system_status.php version
php src/system_status.php systemLoad

# Test cached system status
php src/cached_system_status.php version
php src/cached_system_status.php systemLoad
php src/cached_system_status.php configUrl
php src/cached_system_status.php configLogin

# Test network connectivity
php src/network_check.php network
php src/network_check.php auth
php src/network_check.php service
```

### Manual Zabbix Agent Testing

```bash
# For Zabbix Agent 2 (recommended)
zabbix_agent2 -t abraflexi.company.lld
zabbix_agent2 -t abraflexi.system.version
zabbix_agent2 -t abraflexi.system.appServerRunning
zabbix_agent2 -t abraflexi.network.connectivity
zabbix_agent2 -t abraflexi.network.overall
zabbix_agent2 -t abraflexi.config.url
zabbix_agent2 -t abraflexi.config.login

# For Zabbix Agent (legacy)
zabbix_agentd -t abraflexi.company.lld
zabbix_agentd -t abraflexi.system.version
zabbix_agentd -t abraflexi.system.appServerRunning
zabbix_agentd -t abraflexi.network.connectivity
zabbix_agentd -t abraflexi.network.overall
zabbix_agentd -t abraflexi.config.url
zabbix_agentd -t abraflexi.config.login
```

## Monitoring Intervals & Performance

- **Company LLD**: Every hour (companies don't change frequently)
- **System metrics**: Every minute with 30-second caching
- **Network health**: Every minute
- **Triggers**: Immediate evaluation (no delays)

### Request Load Optimization

| Component | Without Caching | With Caching | Reduction |
|-----------|-----------------|--------------|-----------|
| System metrics | 18 requests/min | 2 requests/min | 89% |
| Network checks | 4 requests/min | 4 requests/min | 0% |
| **Total** | **22 requests/min** | **6 requests/min** | **73%** |

The intelligent caching system ensures that even with minute-by-minute monitoring of all metrics, your AbraFlexi server receives a maximum of 2 requests per minute for system status data.

## Error Codes

The network check script uses specific exit codes for granular error detection:

| Exit Code | Description | Trigger Response |
|-----------|-------------|------------------|
| 0 | Success | No alert |
| 1 | Network connectivity error | Network failure trigger |
| 2 | Authentication failure | Authentication failure trigger |
| 3 | Service unavailable | Service health trigger |
| 4 | Invalid response data | Data error trigger |

## Troubleshooting

### Common Issues

1. **"Failed to retrieve company list"**
   - Check ABRAFLEXI_URL configuration
   - Verify network connectivity
   - Confirm authentication credentials

2. **"Authentication failure"**
   - Check username/password in .env file
   - Verify user has API access permissions
   - Test credentials with AbraFlexi web interface

3. **"Service health check failure"**
   - Check if AbraFlexi service is running
   - Verify `/status.json` endpoint accessibility
   - Check for service startup/shutdown

4. **"zabbix-agent but it is not installable" dependency error**
   - Install Zabbix repository first:
   ```bash
   # For Debian 13 - install Zabbix repository
   wget https://repo.zabbix.com/zabbix/7.4/release/debian/pool/main/z/zabbix-release/zabbix-release_latest_7.4+debian13_all.deb
   sudo dpkg -i zabbix-release_latest_7.4+debian13_all.deb
   sudo apt update
   
   # For Ubuntu 24.04 - install Zabbix repository
   wget https://repo.zabbix.com/zabbix/7.4/release/ubuntu/pool/main/z/zabbix-release/zabbix-release_latest_7.4+ubuntu24.04_all.deb
   sudo dpkg -i zabbix-release_latest_7.4+ubuntu24.04_all.deb
   sudo apt update
   
   # Then install the package
   sudo apt install ../abraflexi-zabbix_1.0.0_all.deb
   
   # Alternative: Install dependencies manually first
   sudo apt install zabbix-agent2
   sudo dpkg -i ../abraflexi-zabbix_1.0.0_all.deb
   ```

5. **No data in Zabbix**
   - Verify zabbix-agent is running
   - Check agent configuration file placement
   - Test manual script execution
   - Review Zabbix server logs

6. **Cache issues**
   - Check `/tmp/abraflexi-zabbix-cache/` directory permissions
   - Clear cache manually: `rm -rf /tmp/abraflexi-zabbix-cache/`
   - Verify disk space in `/tmp`

### Log Files

- **System logs**: `/var/log/syslog` (script errors)
- **Zabbix agent logs**: `/var/log/zabbix/zabbix_agentd.log`
- **AbraFlexi logs**: Check AbraFlexi server logs

## Development

### Building

```bash
# Install dependencies
make vendor

# Run code quality checks
make cs
make static-code-analysis

# Test company LLD
make companylld
```

### File Structure

```
src/
├── company_lld.php          # Company Low Level Discovery
├── system_status.php        # Direct system metrics from /status.json
├── cached_system_status.php # Cached system metrics (30s TTL)
└── network_check.php        # Network connectivity testing

zabbix/
├── abraflexi.conf           # Zabbix Agent user parameters (legacy)
├── abraflexi-agent2.conf    # Zabbix Agent 2 user parameters (recommended)
└── abraflexi-template.xml   # Zabbix monitoring template

bin/
├── abraflexi-zabbix-lld-company # Company discovery wrapper
├── abraflexi-zabbix-status      # Cached status wrapper  
└── abraflexi-zabbix-network     # Network check wrapper
```

## License

MIT License - see LICENSE file for details.

## Support

- **GitHub Issues**: [VitexSoftware/abraflexi-zabbix](https://github.com/VitexSoftware/abraflexi-zabbix)
- **AbraFlexi Documentation**: [FlexiBee API](https://www.flexibee.eu/api/)
- **Zabbix LLD Documentation**: [Low Level Discovery](https://www.zabbix.com/documentation/current/en/manual/discovery/low_level_discovery)

