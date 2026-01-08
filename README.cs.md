Hlášení neodešlaných dokladů
-----------------------------

Skript, který vytváří hlášení o neodešlaných fakturách ve formátu kompatibilním s MultiFlexi.

Od verze 1.3.8 hlášení odpovídají [schématu hlášení MultiFlexi](https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/multiflexi.report.schema.json):

```json
{
  "status": "warning",
  "timestamp": "2025-10-04T01:00:00+00:00",
  "message": "Nalezeno 2 neodešlané faktury ovlivňující 1 společnosti",
  "artifacts": {
    "unsent_invoices": [
      {
        "kod": "VF1-0077/2024",
        "firma": "ZÁKAZNÍK s.r.o.",
        "email": "info@zakaznik.cz"
      }
    ]
  },
  "metrics": {
    "total_unsent": 2,
    "companies_affected": 1
  }
}
```

## Monitorování neodešlaných faktur v Zabbixu

Šablona obsahuje následující položky pro monitorování neodešlaných faktur:

- `abraflexi.invoices.unsent.total` - Celkový počet neodešlaných faktur
- `abraflexi.invoices.unsent.companies` - Počet společností s neodešlanými fakturami
- `abraflexi.invoices.unsent.status` - Stav z kontroly neodešlaných faktur
- `abraflexi.invoices.unsent.message` - Čitelná zpráva o stavu

### Triggery pro neodeslané faktury

- **Vysoký počet neodešlaných faktur** (Upozornění) - Spouští se při > 10 neodešlaných fakturách
- **Kritický počet neodešlaných faktur** (Vysoká) - Spouští se při > 50 neodešlaných fakturách  
- **Více společností má neodeslané faktury** (Upozornění) - Spouští se při > 5 postižených společnostech
- **Neodeslané faktury zjištěny** (Upozornění) - Spouští se při stavu "warning"

### Dashboard

Šablona obsahuje předkonfigurovaný dashboard "AbraFlexi Server Overview" s:
- Graf průměrného zatížení systému
- Graf využití paměti v procentech
- Zobrazení počtu neodešlaných faktur
- Počet postižených společností
- Stavová zpráva o neodešlaných fakturách
- Historický trend neodešlaných faktur

### Instalace

1. Zkopírujte konfiguraci Zabbix agenta:
   ```bash
   sudo cp zabbix/abraflexi-invoices.conf /etc/zabbix/zabbix_agent2.d/
   sudo systemctl restart zabbix-agent2
   ```

2. Importujte šablonu `zabbix/abraflexi-template.xml` do Zabbixu

3. Ujistěte se, že je nainstalován balíček `abraflexi-mailer` se skriptem `ShowUnsent.php`
