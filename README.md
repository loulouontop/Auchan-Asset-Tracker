# Auchan Asset Tracker

GLPI plugin for tracking IT equipment stock, physical containers (QR), allocation to users, inter-location transfers, service via tickets, write-off, dashboards and reports.

**Version:** 1.0.0  
**Author:** Lokmane BENAZIZA  
**License:** Auchan RO  

## Requirements

- GLPI 11.0.2 (min 11.0.0)
- PHP 8.1+

## Install

1. Copy this folder to `plugins/auchanassettracker` (include `public/`).
2. **Setup → Plugins** → Install → Enable.
3. Map roles under **Administration → Profiles → Auchan Asset Tracker**.
4. Create locations and containers before receiving stock.

Details: [docs/installation.md](docs/installation.md).

## Documentation

See **[docs/README.md](docs/README.md)** for architecture, admin/manager/user guides, API notes, QA plans and troubleshooting.

## Locale

Romanian translations: `locales/ro_RO.php` (loaded when GLPI language is `ro_RO`).
