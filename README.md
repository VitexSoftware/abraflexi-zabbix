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
- **Unsent invoices monitoring** with automated alerts:
  - Detection of unsent invoices from AbraFlexi
  - Company-level unsent invoice tracking
  - Status monitoring and alerting
  - Integration with abraflexi-mailer ShowUnsent.php
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
   sudo cp zabbix/abraflexi-invoices.conf /etc/zabbix/zabbix_agent2.d/
   sudo systemctl restart zabbix-agent2
   
   # For Zabbix Agent (legacy)
   sudo cp zabbix/abraflexi.conf /etc/zabbix/zabbix_agentd.d/
   sudo cp zabbix/abraflexi-invoices.conf /etc/zabbix/zabbix_agentd.d/
   sudo systemctl restart zabbix-agent
   ```

4. Import Zabbix template:
   - Open Zabbix web interface
   - Go to Data Collection → Templates
   - Click Import
   - Upload `zabbix/abraflexi-template.xml`
   - Apply template to your AbraFlexi server host

## Zabbix Template

The `zabbix/abraflexi-template.xml` file is a comprehensive Zabbix 7.2 template that provides complete monitoring for AbraFlexi servers.

### Template Contents

#### 📊 Monitoring Items (22 items)

**Network Health Checks:**
- `abraflexi.network.connectivity` - TCP connectivity status (0/1)
- `abraflexi.network.authentication` - API authentication status (0/1)
- `abraflexi.network.service` - Service health status (0/1)

**System Metrics:**
- `abraflexi.system.version` - AbraFlexi version string
- `abraflexi.system.version.numeric` - Numeric version for comparison (dependent item)
- `abraflexi.system.appServerRunning` - Application server running status (0/1)
- `abraflexi.system.systemLoad` - System load average (float)
- `abraflexi.system.memoryUsed` - Memory used in bytes
- `abraflexi.system.memoryHeap` - JVM heap size in bytes
- `abraflexi.system.memoryUsagePercent` - Calculated memory usage percentage
- `abraflexi.system.loggedUser` - Number of logged users
- `abraflexi.system.sessions` - Number of active sessions
- `abraflexi.system.bytesRead` - Total bytes read
- `abraflexi.system.totalGcTime` - Total garbage collection time (ms)
- `abraflexi.system.responseTime` - API response time (ms)
- `abraflexi.system.javaVersion` - Java runtime version
- `abraflexi.system.licenseName` - License name
- `abraflexi.system.licenseVariant` - License variant/type

**Unsent Invoices Monitoring:**
- `abraflexi.invoices.unsent.total` - Total number of unsent invoices
- `abraflexi.invoices.unsent.companies` - Number of companies with unsent invoices
- `abraflexi.invoices.unsent.status` - Overall status from unsent invoices check
- `abraflexi.invoices.unsent.message` - Human-readable status message

#### 🚨 Triggers (19 triggers)

**Critical/Disaster Priority:**
- Network connectivity failure
- Authentication failure
- Service health check failure
- Application server down

**High Priority:**
- Critical system load (> 10.0)
- Critical memory usage (> 95%)
- Critical number of logged users (> 100)
- Critical number of active sessions (> 150)
- Critical number of unsent invoices (> 50)
- Company unavailable (per discovered company)

**Warning Priority:**
- High system load (> 5.0)
- High memory usage (> 85%)
- High number of logged users (> 50)
- High number of active sessions (> 75)
- High number of unsent invoices (> 10)
- Multiple companies have unsent invoices (> 5)
- Unsent invoices detected (status = warning)
- Slow API response time (> 5000ms)

**Info Priority:**
- AbraFlexi version updated
- AbraFlexi version downgraded

## Unsent mail reporter

Script which produces reports of unsent invoices in MultiFlexi-compliant format.

Since version 1.3.8, reports conform to the [MultiFlexi report schema](https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json):

```json
{
  "status": "warning",
  "timestamp": "2025-10-04T01:00:00+00:00",
  "message": "2 unsent invoices found affecting 1 companies",
  "artifacts": {
    "unsent_invoices": [
      {
        "id": 1131,
        "firma": {
          "value": "code:CUSTOMER",
          "target": "adresar",
          "ref": "/c/vitex_software/adresar/827.odeslat')",
          "showAs": "CUSTOMER: CUSTOMER l.t.d."
        },
        "kontaktEmail": "info@customer.com",
        "poznam": "",
        "kod": "VF1-0077/2024",
        "email": "info@customer.com",
        "recipients": "info@customer.com"
      }
    ]
  },
  "metrics": {
    "total_unsent": 2,
    "companies_affected": 1
  }
}
```

#### 🔍 Discovery Rules

**Company Discovery (LLD):**
- `abraflexi.company.lld` - Discovers all AbraFlexi companies/databases
- Runs every 1 hour
- Creates item prototypes for each discovered company:
  - `abraflexi.company.available[{#COMPANY_CODE}]` - Company availability status
- Creates trigger prototypes:
  - Company unavailability alerts (High priority)
- Creates graph prototypes:
  - **Company {#COMPANY_NAME} Availability** - Automatic graph for each company showing availability over time

#### 📊 Visualizing Data

**Graph Prototypes (Automatic):**
- Each discovered company automatically gets an availability graph
- View at: **Monitoring → Hosts → [Your Host] → Graphs**

**Creating Custom Graphs:**
To visualize system metrics, create graphs manually in Zabbix:

1. Go to **Data collection → Hosts**
2. Click on your AbraFlexi host
3. Click **Graphs → Create graph**
4. Add items you want to visualize (e.g., Memory Usage, System Load, API Response Time)

**Recommended Graphs:**
- **System Performance**: `abraflexi.system.systemLoad`
- **Memory Usage**: `abraflexi.system.memoryUsed` + `abraflexi.system.memoryHeap`
- **Memory %**: `abraflexi.system.memoryUsagePercent`
- **Network Health**: `abraflexi.network.connectivity` + `abraflexi.network.authentication` + `abraflexi.network.service`
- **User Activity**: `abraflexi.system.loggedUser` + `abraflexi.system.sessions`
- **API Performance**: `abraflexi.system.responseTime`

**Creating Dashboards:**
The template includes a pre-configured dashboard "AbraFlexi Server Overview" with:
- System Load Average graph
- Memory Usage percentage graph  
- Unsent Invoices count display
- Companies affected by unsent invoices count
- Unsent invoices status message
- Historical trend graph for unsent invoices

Create additional custom dashboards in Zabbix UI:
1. Go to **Monitoring → Dashboards → Create dashboard**
2. Add widgets (graphs, problems, item values, etc.)
3. Reference items from your AbraFlexi host

#### 🗺️ Value Maps

**Service State:**
- `0` → "Down"
- `1` → "Up"

Used by network connectivity, authentication, service health, and app server status items.

### Template Features

- **Zabbix Version**: 7.2
- **Template Group**: Templates/AbraFlexi
- **Tags**: `label:AbraFlexi`
- **History Retention**: 30 days for most items
- **Trends Retention**: 365 days for numeric items
- **Update Interval**: 1 minute for most items (configurable)
- **Intelligent Caching**: 30-second cache reduces API load by 73%


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

#### Unsent Invoices Monitoring
- `abraflexi.invoices.unsent.total` - Total number of unsent invoices
- `abraflexi.invoices.unsent.companies` - Number of companies with unsent invoices  
- `abraflexi.invoices.unsent.status` - Status from unsent invoices check
- `abraflexi.invoices.unsent.message` - Human-readable status message

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
- **High number of unsent invoices** - More than 10 unsent invoices
- **Multiple companies with unsent invoices** - More than 5 companies affected
- **Unsent invoices detected** - Status indicates unsent invoices present

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

# Test unsent invoices monitoring (requires abraflexi-mailer)
# Note: ShowUnsent.php should be available from abraflexi-mailer package
php /usr/share/abraflexi-mailer/src/ShowUnsent.php
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
zabbix_agent2 -t abraflexi.invoices.unsent.total
zabbix_agent2 -t abraflexi.invoices.unsent.companies
zabbix_agent2 -t abraflexi.invoices.unsent.status
zabbix_agent2 -t abraflexi.invoices.unsent.message

# For Zabbix Agent (legacy)
zabbix_agentd -t abraflexi.company.lld
zabbix_agentd -t abraflexi.system.version
zabbix_agentd -t abraflexi.system.appServerRunning
zabbix_agentd -t abraflexi.network.connectivity
zabbix_agentd -t abraflexi.network.overall
zabbix_agentd -t abraflexi.config.url
zabbix_agentd -t abraflexi.config.login
zabbix_agentd -t abraflexi.invoices.unsent.total
zabbix_agentd -t abraflexi.invoices.unsent.companies
zabbix_agentd -t abraflexi.invoices.unsent.status
zabbix_agentd -t abraflexi.invoices.unsent.message
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
├── abraflexi-invoices.conf  # Unsent invoices monitoring configuration
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



## Update monitoring

The template and agent configuration include items that track the latest available
AbraFlexi versions from both Vitex testing and official production repositories:

- `abraflexi.update.latestTesting` / `abraflexi.update.latestTesting.numeric`
- `abraflexi.update.latestProduction` / `abraflexi.update.latestProduction.numeric`

The numeric items are derived from the text versions and used in triggers that raise
alerts when your running AbraFlexi version is older than the latest testing or
production release. Data are obtained via external scripts installed as
`/usr/lib/zabbix/externalscripts/abraflexi-update-testing.sh` and
`/usr/lib/zabbix/externalscripts/abraflexi-update-production.sh`.
