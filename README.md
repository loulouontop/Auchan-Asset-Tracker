# Auchan Asset Tracker — Sprint 1

GLPI plugin for IT stock receipt into physical containers, with role mapping and audit trail.

**Version:** 0.1.20 (Sprint 1)  
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

1. Copy this folder to `plugins/` and keep **one** name only (recommended: `auchanassettracker`).
   - On Linux the folder name is case-sensitive. Do **not** keep both `auchanassettracker` and `AuchanAssetTracker`.
   - The plugin syncs `glpi_plugins.directory` to the exact folder name on disk so menus and links stay aligned.
2. **Setup → Plugins** → Install → Enable (or open the page once after an update — self-heal finishes the upgrade).
3. Map roles under **Administration → Profiles → Auchan Asset Tracker**.
4. Create locations and containers before receiving stock.

## Acceptance check

Manager creates a shelf (container), adds a laptop into it, sees it in stock as Available.
