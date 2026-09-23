# Manager / technician guide

## Containers first

1. Create a **physical container** (shelf/box) for your location.
2. Print the QR label (*Download PDF label*) and stick it on the shelf.
3. You cannot receive stock without a container.

## Receive equipment

- **Single:** *Equipment → Add* — type, serial (Category A), model, manufacturer, **container**.
- **Bulk accessories:** *Configuration / Bulk add accessories* — type + quantity (creates N records).

Status becomes **Available** at your location.

## Allocate to user

1. *New allocation* → pick user → see gear they already have.
2. Select Available items → Allocate.
3. Status → **Awaiting validation** (container cleared).
4. User confirms or rejects. Overdue items appear on your dashboard.

If user chooses **Did not receive**, put the item back in a container.

## Transfer

1. *Transfers → New* → destination + items.
2. Status → **In transit**.
3. Destination manager validates and **must assign a container** per item.
4. If no container exists yet, create one first (guided).

## Write-off / Lost / Stolen

Open the equipment → reason required → optional document reference.

## Service

When the user opens a GLPI ticket on the gear, status becomes **In service**. After the ticket is solved/closed, it returns to **Allocated** (same user).
