# Auchan Asset Tracker

GLPI plugin for IT equipment stock, physical containers, and allocation with user confirmation.

**Version:** 0.2.0 (Sprint 2)  
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

## Sprint 2 scope

- Stock + containers (Sprint 1)
- Allocate Available → Awaiting validation (container cleared)
- User Confirm / Did not receive
- Overdue confirmation alerts (configurable working days, default 5)
- Allocation history + email notice (in-app fallback if mail unset)

## Locale

Romanian translations: `locales/ro_RO.php` (loaded when GLPI language is `ro_RO`).
