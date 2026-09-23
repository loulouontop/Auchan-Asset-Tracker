# Technical architecture

## Plugin folder layout

```
auchanassettracker/
├── setup.php              # Init, hooks, version, translation load
├── hook.php               # Install / upgrade / uninstall
├── install/install.sql    # Schema (CREATE IF NOT EXISTS)
├── front/                 # Authenticated UI (dashboard, CRUD, workflows)
├── public/                # Unauthenticated QR pages + assets
│   ├── qr.php             # Public container view (?t=TOKEN)
│   ├── qrimg.php          # QR image generator
│   ├── css/               # Styles (also referenced via hooks)
│   └── js/
├── inc/                   # Classes (equipment, container, allocation, …)
├── locales/               # PHP array translations (e.g. ro_RO.php)
├── ajax/                  # AJAX endpoints (if any)
├── templates/
└── docs/
```

GLPI registers the plugin under `plugins/auchanassettracker`. Menu is added under **Assets**.

## Data model

All tables are prefixed `glpi_plugin_auchanassettracker_`. Uninstall keeps tables.

| Logical name | Table | Role |
|--------------|-------|------|
| configs | `…_configs` | Key/value thresholds |
| equipmenttypes | `…_equipmenttypes` | Type catalog (category A = serial required, B = accessories) |
| manufacturers | `…_manufacturers` | Manufacturer catalog |
| containers | `…_containers` | Physical boxes/shelves + `qr_token`, location |
| equipments | `…_equipments` | Tracked items (status, location, container, user, service fields) |
| allocations | `…_allocations` | Allocation history + confirmation status |
| transfers | `…_transfers` | Inter-location transfers |
| transferitems | `…_transferitems` | Equipment lines on a transfer (+ destination container on validate) |
| auditlogs | `…_auditlogs` | Action audit trail |
| profiles | `…_profiles` | GLPI profile → plugin role + scoped location |

**Equipment statuses:** `available`, `awaiting_validation`, `allocated`, `in_transit`, `in_service`, `written_off`, `lost`, `stolen`.

**Allocation statuses:** `pending`, `confirmed`, `rejected`, `returned`.

**Transfer statuses:** typically `in_transit` then validated (completed).

## Workflows

### Allocation
1. Tech selects available stock → recipient → **Allocate**.
2. Equipment → `awaiting_validation`; allocation `pending`; user notified.
3. User **Confirm receipt** → `allocated` / `confirmed`, or **Did not receive** → manager returns item to a container.

### Transfer
1. Source location starts transfer of available items → `in_transit`.
2. Destination validates: each item must be assigned a **local container**.
3. Items become `available` at destination in that container.

### Service (ticket hook)
- Ticket create/update or `Item_Ticket` link can move matching equipment to `in_service`.
- Ticket solved/closed returns equipment to `allocated`.

### Write-off
- Manager/admin marks **Written off / Lost / Stolen** with mandatory reason.
- Only **central_admin** can change final statuses afterward or **reintroduce into stock** (container required).

## Roles

Mapped on GLPI Profile → **Auchan Asset Tracker** tab (`profiles` table):

| Role key | Typical scope |
|----------|----------------|
| `central_admin` | All locations; config, reports, final statuses, reintroduce |
| `location_manager` | One location; stock, containers, write-off |
| `support_tech` | Scoped location; allocate, transfer, reports (with admin) |
| `user` | Own dashboard: confirm receipt, my equipment |

Super-admins with `config` UPDATE and no mapping act as central admin.

## Hooks

Registered in `setup.php` when a user is logged in:

| Hook | Itemtype | Handler |
|------|----------|---------|
| `item_add` | `Ticket` | `PluginAuchanassettrackerTickethook::postTicketAdd` |
| `item_add` | `Item_Ticket` | `…::postItemTicketAdd` |
| `item_update` | `Ticket` | `…::postTicketUpdate` |

Also: `menu_toadd` (assets), `config_page`, `add_css`, `csrf_compliant`, `post_init` (translations).

## Public QR URL

```
plugins/auchanassettracker/public/qr.php?t=TOKEN
```

- No login (`DO_NOT_CHECK_LOGIN`).
- Token = container `qr_token`.
- Shows available equipment in that container, grouped by type (read-only).
- Printable label: `front/container.qr.php?id=…` (authenticated).

## Coexistence with `auchanequipment`

- Separate plugin directory and tables; **no shared schema**.
- Both may run on the same GLPI instance.
- Ticket serial matching in Asset Tracker is independent of the first plugin’s FAR/service-card logic.
- Do not rename or drop `auchanequipment` tables when installing this plugin.
