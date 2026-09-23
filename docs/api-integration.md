# API / integration reference

## Public QR endpoint

| Method | Path | Auth |
|--------|------|------|
| GET | `/plugins/auchanassettracker/public/qr.php?t={token}` | None |
| GET | `/plugins/auchanassettracker/public/qrimg.php?t={token}` | None (PNG) |

Returns read-only HTML of **Available** equipment in that container, grouped by type. Inactive/unknown token → 404.

## Authenticated front controllers

All under `/plugins/auchanassettracker/front/` (GLPI session required):

| Page | Purpose |
|------|---------|
| `dashboard.php` | Role dashboard |
| `equipment.php` / `.form.php` / `.bulk.php` | Stock |
| `container.php` / `.form.php` / `.qr.php` | Containers + label |
| `allocation.form.php` | Allocate |
| `confirm.php` | User confirm/reject |
| `transfer.php` / `.form.php` | Transfers |
| `report.php` | Reports (+ `?export=csv`) |
| `config.form.php` | Thresholds |

## Ticket → In service

Hooks: `item_add` / `item_update` on `Ticket` and `Item_Ticket`.

Match equipment by:

1. Marker `AAT-EQ-{id}` in ticket name/content
2. Serial-like tokens in text matching `equipments.serial`
3. Linked GLPI asset serial

On ticket **Solved/Closed**: equipment with `service_tickets_id` returns to **Allocated**.
