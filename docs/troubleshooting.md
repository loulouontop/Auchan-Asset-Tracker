# Troubleshooting

## Logging

Dedicated file:

```
{GLPI_LOG_DIR}/plugin_auchanassettracker.log
```

Written via `PluginAuchanassettrackerPluginlog` (Toolbox::logInFile). Check after mail failures, unexpected exceptions, or silent ticket hooks.

Also review GLPI `php-errors.log` / web server error log for fatals.

## Diagnostic checklist — stuck statuses

| Symptom | Check |
|---------|--------|
| Stuck `awaiting_validation` | Pending row in `allocations`? User confirmed? Threshold alerts on dashboard |
| Stuck `in_transit` | Open transfer; destination validated? Containers assigned? |
| Stuck `in_service` | Ticket still open? `service_tickets_id` set? Close/solve ticket to return to allocated |
| Cannot allocate | Status must be `available`; no other pending allocation; same location |
| Cannot validate transfer | Logged user scoped to **destination** location; containers exist there |
| Cannot edit final status | Only central_admin |
| QR 404 | Token matches `containers.qr_token`; container active; public path reachable |
| Empty stock after reject | Manager must place item back in a container |

## Sample read-only SQL

```sql
-- Equipment overview
SELECT id, name, serial, status, locations_id,
       plugin_auchanassettracker_containers_id AS container_id,
       users_id, service_tickets_id, service_since
FROM glpi_plugin_auchanassettracker_equipments
WHERE is_deleted = 0
ORDER BY id DESC
LIMIT 50;

-- Containers
SELECT id, code, name, locations_id, qr_token, is_active
FROM glpi_plugin_auchanassettracker_containers
WHERE is_deleted = 0;

-- Pending allocations
SELECT a.id, a.plugin_auchanassettracker_equipments_id AS eq_id,
       a.users_id_recipient, a.allocation_status, a.allocation_date
FROM glpi_plugin_auchanassettracker_allocations a
WHERE a.allocation_status = 'pending'
ORDER BY a.allocation_date ASC;

-- Recent audit
SELECT id, users_id, action, itemtype, items_id, details, date_creation
FROM glpi_plugin_auchanassettracker_auditlogs
ORDER BY id DESC
LIMIT 100;
```

## Rollback

1. Disable plugin in **Setup → Plugins**.
2. Restore previous plugin zip into `plugins/auchanassettracker`.
3. Enable previous version.
4. **Tables are kept** on uninstall — do not DROP unless intentional data wipe.

## Version checklist

| Component | Required |
|-----------|----------|
| GLPI | **11.0.2** (plugin min 11.0.0) |
| PHP | **8.1+** |
| Plugin directory name | `auchanassettracker` |
| Plugin version | 1.0.0 |
