# Auchan Asset Tracker — Sprint 1

GLPI plugin for IT stock receipt into physical containers, with role mapping and audit trail.

**Version:** 0.1.8 (Sprint 1)  
**Author:** Lokmane BENAZIZA  
**License:** Auchan RO  

## Sprint 1 scope

- Physical containers (CRUD, per location, soft-delete)
- Equipment receipt (single + bulk accessories) → status **Available**
- Mandatory container when equipment is in stock
- Four roles: Central admin, Location manager, Support technician, End user
- Audit log on create/update
- Locales: English + Romanian (`ro_RO`)

## Requirements

- GLPI 11.0.2 (min 11.0.0)
- PHP 8.1+

## Install

1. Copy this folder to `plugins/auchanassettracker`.
2. **Setup → Plugins** → Install → Enable.
3. Map roles under **Administration → Profiles → Auchan Asset Tracker**.
4. Create locations and containers before receiving stock.

## Acceptance check

Manager creates a shelf (container), adds a laptop into it, sees it in stock as Available.
