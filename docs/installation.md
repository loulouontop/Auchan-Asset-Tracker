# Installation

**Targets:** GLPI 11.0.2, PHP 8.1+.

## Prerequisites

- GLPI 11.0.2 (min 11.0.0, max &lt; 11.10 per plugin metadata)
- PHP 8.1+
- DB user with CREATE/ALTER on GLPI database
- Write access to `GLPI_LOG_DIR` for `plugin_auchanassettracker.log`

## Fresh install

1. Copy the plugin folder to GLPI as **`plugins/auchanassettracker`** (name must match). Include `public/`, `front/`, `inc/`, `install/`, `locales/`.
2. Ensure web server can serve `plugins/auchanassettracker/public/` (QR pages).
3. In GLPI: **Setup → Plugins** → **Install**, then **Enable** Auchan Asset Tracker.
4. Install runs `install/install.sql` (`CREATE TABLE IF NOT EXISTS`) and seeds default configs, equipment types, manufacturers, and profile rights.
5. Map GLPI profiles → plugin roles: **Administration → Profiles → [profile] → Auchan Asset Tracker** (role + location for managers/techs).
6. **Create locations** (GLPI) and **physical containers** (plugin) **before** receiving equipment into stock. Stock receipt requires a container at that location.

## Upgrade

1. Disable the plugin (optional but safer).
2. Replace files under `plugins/auchanassettracker` with the new version (keep same directory name).
3. Enable / update from **Setup → Plugins**. Upgrade re-applies `install.sql` (IF NOT EXISTS), re-seeds defaults, refreshes version, clears translation cache.
4. Smoke-test: dashboard, one allocation confirm, one QR URL, ticket → in service.

## Rollback

1. **Disable** the plugin in GLPI.
2. Restore the previous plugin zip/files into `plugins/auchanassettracker`.
3. Enable the previous version.
4. Tables are **not** dropped on uninstall — data remains. Do not manually DROP unless you intend a full wipe.

## Post-install checklist

- [ ] Plugin enabled, version 1.0.0
- [ ] Profile roles mapped
- [ ] At least one location + one container
- [ ] Language `ro_RO` loads `locales/ro_RO.php` (non-English)
- [ ] Public QR reachable without login
