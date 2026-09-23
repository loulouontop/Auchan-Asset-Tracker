# Test plan

Columns: **ID** | **Scenario** | **Steps** | **Expected** | **Pass/Fail**

Fill Pass/Fail during execution; see [test-execution-report.md](test-execution-report.md).

## Equipment (ECH)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| ECH-001 | Create category A with serial | Manager adds type A item with unique serial + container | Status available; serial stored | |
| ECH-002 | Category A without serial rejected | Add type A without serial | Error: serial required | |
| ECH-003 | Duplicate serial rejected | Add second item with same serial | Error: serial must be unique | |
| ECH-004 | Stock requires container | Add available item without container | Error: container mandatory | |
| ECH-005 | Bulk accessories | Bulk add N category B items into container | N records created, available | |

## Allocation (ALO)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| ALO-001 | Allocate available item | Tech allocates to user | Status awaiting_validation; pending allocation | |
| ALO-002 | Cannot allocate non-available | Try allocate in_transit item | Error: only available | |
| ALO-003 | Location scope | Tech from loc A allocates loc B stock | Error: another location | |
| ALO-004 | Pending conflict | Second allocate while first pending | Error: cancel pending first | |
| ALO-005 | Show current gear | Select user → Show current gear | Lists user’s equipment or None | |
| ALO-006 | Multi-select allocate | Select several available → Allocate | Count message; all pending | |

## Validation / confirmation (VAL)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| VAL-001 | Confirm receipt | User confirms | Allocated + confirmed; leaves pending list | |
| VAL-002 | Did not receive | User rejects | Manager notified; item to be re-containered | |
| VAL-003 | Empty confirm list | User with no pending | “Nothing to confirm.” | |
| VAL-005 | Overdue unconfirmed alert | Leave pending past threshold | Dashboard/report shows unconfirmed alert | |

## Transfer (TRF)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| TRF-001 | Start transfer | Source selects dest + items | Transfer in_transit; items in_transit | |
| TRF-002 | Same location rejected | Dest = source | Error: must differ | |
| TRF-003 | Non-available rejected | Include allocated item | Error: only available | |
| TRF-004 | Wrong source location | Item from other location | Error: must belong to source | |
| TRF-005 | Validate with containers | Dest assigns container per line → Validate | Available at dest in containers | |
| TRF-006 | Validate without container | Leave container empty | Error: each must be placed | |
| TRF-007 | No dest container UI | Dest has zero containers | Warning + link to create container | |

## Write-off / final (REP)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| REP-001 | Write-off with reason | Manager applies written_off + reason | Status written_off; reason stored | |
| REP-002 | Lost | Apply lost + reason | Status lost | |
| REP-003 | Stolen | Apply stolen + reason | Status stolen | |
| REP-004 | Reason mandatory | Apply without reason | Error: reason mandatory | |
| REP-005 | Non-admin cannot change final | Manager edits final status item | Error: only Central Admin | |

## Reintroduce / central (CSR)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| CSR-001 | Reintroduce to stock | Admin picks container → Reintroduce | Available in container | |
| CSR-002 | Reintroduce wrong location container | Container of other location | Error: container location | |
| CSR-003 | Config thresholds save | Change three thresholds → Save | “Thresholds saved.”; values persist | |
| CSR-004 | Profile role mapping | Map profile to location_manager + location | “Role mapping saved.” | |
| CSR-005 | Seeded types/manufacturers | Fresh install | Defaults present and usable | |

## QR (QR)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| QR-001 | Public URL loads | Open qr.php?t=valid | Container header + available items | |
| QR-002 | Invalid token | Open qr.php?t=bad | 404 not found | |
| QR-003 | Empty container | QR for empty stock container | Empty message | |
| QR-004 | Group by type | Mixed types in container | Groups with counts | |
| QR-005 | Print label | container.qr.php?id= | Label HTML with QR + print | |
| QR-006 | No login required | Incognito public URL | Content without GLPI login | |

## Dashboard (DSH)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| DSH-001 | Admin totals | Open dashboard as central_admin | Total + status tiles | |
| DSH-002 | Active alerts list | Seed overtime/unconfirmed/transfer | Alerts rendered | |
| DSH-003 | No alerts | Clean data | “No active alerts.” | |
| DSH-004 | Stock by location | Admin view | Per-location available/allocated/service/transit | |
| DSH-005 | Manager tech room | Manager dashboard | Container stock table | |
| DSH-006 | To resolve today | Pending transfers/unconfirmed | Action list | |
| DSH-007 | User pending banner | User with pending | Count + Confirm link | |
| DSH-008 | My equipment | User with confirmed gear | Table without awaiting items | |
| DSH-009 | Recent activity | Perform allocate | Audit appears in recent activity | |

## Reports (RAP)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| RAP-001 | Inventory by location | Run report | Rows of equipment at location | |
| RAP-002 | Equipment per user | Filter user | User’s gear + allocation date | |
| RAP-003 | Movements period | Date range | Audit movements | |
| RAP-004 | Stock by type | Run | Quantities by type/location | |
| RAP-005 | Issues report | Seed overtime/unconfirmed/transfer | Issue rows with days | |
| RAP-006 | Inventory by container | Filter container | Items in container | |
| RAP-007 | CSV export | Run + Export CSV | Downloadable CSV | |

## Security / rights (SEC)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| SEC-001 | User cannot open config | User hits config.form.php | Access denied | |
| SEC-002 | Cross-location container create | Manager loc A creates for loc B | Blocked / forced to scope | |
| SEC-003 | Cross-location equipment edit | Manager edits other loc item | Error: another location | |
| SEC-004 | Only dest validates transfer | Source tries validate | Error: only destination | |
| SEC-005 | CSRF / session | POST without session | Rejected by GLPI | |

## Commands / ops (CMD)

| ID | Scenario | Steps | Expected | Pass/Fail |
|----|----------|-------|----------|-----------|
| CMD-002 | Install enable | Install + Enable on clean DB | Tables created; plugin active 1.0.0 | |
| CMD-003 | Upgrade idempotent | Re-run upgrade / reinstall files | No data loss; IF NOT EXISTS OK | |
| CMD-004 | Uninstall keeps tables | Uninstall plugin | Tables still present | |
| CMD-005 | Locale ro_RO | Set language Romanian | UI strings from locales/ro_RO.php | |
