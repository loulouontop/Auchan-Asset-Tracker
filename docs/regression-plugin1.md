# Regression — auchanequipment (plugin 1)

Both plugins installed and enabled. Asset Tracker must not break the first plugin.

**Environment:** GLPI 11.0.2 · auchanequipment + auchanassettracker · Tester: ______ · Date: ______

| # | Check | Steps | Expected | OK? |
|---|-------|-------|----------|-----|
| 1 | Plugin list | Setup → Plugins | Both Installed/Enabled; no fatal errors | |
| 2 | Menus | Open Assets / plugin menus | Both menus present; no PHP warnings | |
| 3 | Tickets — serial (SN) | Create ticket mentioning equipment SN used by auchanequipment | First plugin SN behaviour unchanged | |
| 4 | Tickets — Asset Tracker marker | Ticket with `AAT-EQ-{id}` | Only Asset Tracker moves matching item to in_service; auchanequipment data untouched | |
| 5 | FAR import | Run usual FAR import on plugin 1 | Import succeeds; no writes to `glpi_plugin_auchanassettracker_*` | |
| 6 | Service card | Open service card / workflow in auchanequipment | Card loads; statuses as before | |
| 7 | Shared tables | Inspect DB table list | No shared/renamed tables between plugins | |
| 8 | Uninstall Asset Tracker | Disable/uninstall Asset Tracker only | auchanequipment still works; its tables intact | |
| 9 | Re-enable Asset Tracker | Enable again | Both OK; Asset Tracker tables still present | |
| 10 | Concurrent ticket | Ticket with both SN styles if applicable | Each plugin reacts only to its own rules | |

## Notes

_
_
_
