# REST API (v1)

Stockroom exposes a token-authenticated JSON API under `/api/v1`. It was built
for the [Home Assistant integration](./home-assistant-integration.md) but it's a
general-purpose API — anything that can send an HTTP request with a bearer token
can read inventory, search, and (with a write token) create/update items and
manage Home Assistant links.

This page is the reference: authentication, conventions, and every endpoint.

## Authentication

The API uses [Laravel Sanctum](https://laravel.com/docs/sanctum) **personal
access tokens**. It is stateless — there is no session or CSRF; every request
carries the token in an `Authorization` header:

```
Authorization: Bearer <token>
```

### Getting a token

1. Sign in to Stockroom and open **Settings → API tokens**.
2. Give the token a name (e.g. `Home Assistant`) and pick its abilities:
   - **Read** — statistics, items, rooms, tags, search.
   - **Write** — create/update items and set/remove Home Assistant links.
3. Create it and **copy the token immediately** — it is shown only once.

A token may hold one or both abilities. Issue a **read-only** token to anything
that just needs to poll, and a **write** token only where you genuinely mutate
inventory. Revoke any token from the same screen.

### Auth errors

| Status | Meaning |
| ------ | ------- |
| `401 Unauthorized` | Missing, malformed, or revoked token. |
| `403 Forbidden` | Valid token, but it lacks the ability the route requires (e.g. a read-only token calling a write endpoint). |
| `404 Not Found` | The item id doesn't exist. |
| `422 Unprocessable Entity` | Validation failed; body is `{ "message": ..., "errors": { field: [..] } }`. |
| `429 Too Many Requests` | Rate limit exceeded (see below). |

## Conventions

- **Base URL**: `https://<your-stockroom-host>/api/v1`
- **Versioning**: the path is versioned (`/v1`). Breaking changes ship under a new prefix.
- **Envelope**: resource responses are wrapped in a `data` key (Laravel API
  Resources). List endpoints add `links` and `meta` for pagination. The two
  non-resource endpoints — `statistics` and `search` — return a plain object.
- **Rate limit**: 120 requests/minute per token (falling back to client IP).
  Standard `X-RateLimit-*` headers are returned.
- **IDs** are integers. **Money** fields (`purchase_price`, `value`) are strings
  or numbers with two decimals. **Dates** are `YYYY-MM-DD`; timestamps are ISO-8601.

## Endpoints

All paths are relative to `/api/v1`. The **Ability** column is the token ability
the route requires.

| Method | Path | Ability | Purpose |
| ------ | ---- | ------- | ------- |
| GET | `/user` | read | Token introspection — the authenticated account. |
| GET | `/statistics` | read | Inventory roll-up (counts, value, breakdowns). |
| GET | `/items` | read | Paginated item list with filters. |
| GET | `/items/{item}` | read | Full detail for one item. |
| GET | `/rooms` | read | All rooms (top-level locations) with child counts. |
| GET | `/tags` | read | All tags. |
| GET | `/search?q=` | read | Hybrid keyword + semantic search. |
| GET | `/home-assistant-links` | read | Every item that has a Home Assistant link, with the link embedded. |
| GET | `/items/{item}/maintenance-tasks` | read | Maintenance schedules on an item. |
| GET | `/items/{item}/battery` | read | Current battery level, type, depletion forecast and reminder. |
| POST | `/items` | write | Create an item. |
| PATCH | `/items/{item}` | write | Partially update an item (incl. `battery_type`). |
| PUT | `/items/{item}/home-assistant-link` | write | Set or replace the item's Home Assistant link. |
| DELETE | `/items/{item}/home-assistant-link` | write | Remove the link. |
| POST | `/items/{item}/maintenance-tasks` | write | Create a maintenance reminder. |
| POST | `/maintenance-tasks/{task}/complete` | write | Mark a task done, rolling its schedule. |
| POST | `/items/{item}/battery-readings` | write | Push a battery level sample. |
| POST | `/items/{item}/battery-changes` | write | Record an explicit battery swap. |

### `GET /user`

Validates a token and identifies the account behind it — handy for a client's
"test connection" step.

```bash
curl -s https://stockroom.example/api/v1/user \
  -H "Authorization: Bearer $TOKEN"
```

```json
{ "id": 1, "name": "Jeff", "email": "jeff@example.test" }
```

### `GET /statistics`

```json
{
  "total": 142,
  "value": 8421.5,
  "by_type": { "room": 6, "container": 18, "item": 118 },
  "by_tag": [
    { "id": 3, "name": "Powertools", "slug": "powertools", "color": "#22c55e", "items_count": 12 }
  ],
  "by_room": [
    { "id": 1, "name": "Garage", "icon": "home", "children_count": 24 }
  ],
  "maintenance": { "overdue": 2, "due_soon": 1 }
}
```

`value` excludes sold items. `by_tag`/`by_room` are ordered fullest-first.
`maintenance.overdue` counts active tasks past due; `maintenance.due_soon`
counts active tasks inside their per-task reminder window but not yet overdue.

### `GET /items`

Paginated list of `ItemSummaryResource`. Query parameters (all optional, combinable):

| Param | Type | Effect |
| ----- | ---- | ------ |
| `type` | `room`\|`container`\|`item` | Filter by item type. |
| `parent` | int | Direct children of this item only. |
| `room` | int | The whole subtree beneath this item (any depth). |
| `tag` | int | Items carrying this tag. |
| `has_ha_link` | bool | `1` → only items with a Home Assistant link; `0` → only those without. |
| `per_page` | int | Page size (default 50, max 100). |
| `page` | int | Page number. |

```bash
curl -s "https://stockroom.example/api/v1/items?room=1&has_ha_link=0&per_page=20" \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "data": [
    {
      "id": 42,
      "name": "Cordless Drill",
      "type": { "value": "item", "label": "Item" },
      "parent_id": 7,
      "location_path": "Garage / Toolbox",
      "quantity": 1,
      "thumb_url": "https://stockroom.example/storage/item-images/9/thumb.webp",
      "has_ha_link": false
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 20, "total": 24, "last_page": 2 }
}
```

### `GET /items/{item}`

Full `ItemResource`: all detail/acquisition/warranty/sale fields plus `tags`,
`custom_fields`, and `home_assistant_link` (`null` when unlinked).

```json
{
  "data": {
    "id": 42,
    "name": "Cordless Drill",
    "description": null,
    "parent_id": 7,
    "type": { "value": "item", "label": "Item" },
    "icon": null,
    "location_path": "Garage / Toolbox",
    "quantity": 1,
    "manufacturer": "Bosch",
    "model_number": "GSR 18V-55",
    "serial_number": null,
    "battery_type": "18650",
    "purchase_price": "89.99",
    "purchase_date": "2025-03-01",
    "lifetime_warranty": false,
    "warranty_expires": null,
    "tags": [{ "id": 3, "name": "Powertools", "slug": "powertools", "color": "#22c55e" }],
    "custom_fields": [],
    "home_assistant_link": {
      "ha_entity_id": "sensor.drill_battery",
      "ha_device_id": "abc123",
      "friendly_name": "Drill",
      "url": "http://homeassistant.local:8123/config/devices/device/abc123",
      "instance_id": null,
      "created_at": "2026-06-03T10:00:00+00:00",
      "updated_at": "2026-06-03T10:00:00+00:00"
    },
    "created_at": "2025-03-01T09:00:00+00:00",
    "updated_at": "2026-06-03T10:00:00+00:00"
  }
}
```

### `GET /rooms`

Flat list of `room`-type items with a direct-child count. `parent_id` lets a
client reconstruct any nesting; Home Assistant maps its areas onto these.

```json
{ "data": [ { "id": 1, "name": "Garage", "icon": "home", "parent_id": null, "location_path": "", "children_count": 24 } ] }
```

### `GET /tags`

```json
{ "data": [ { "id": 3, "name": "Powertools", "slug": "powertools", "color": "#22c55e" } ] }
```

### `GET /search?q=`

Reuses Stockroom's Meilisearch-backed hybrid search (keyword + semantic). Returns
the top 20 hits with their location path — the "where is X?" lookup. A blank `q`
returns an empty list.

```bash
curl -s "https://stockroom.example/api/v1/search?q=drill" \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "results": [
    {
      "id": 42,
      "name": "Cordless Drill",
      "type": { "value": "item", "label": "Item" },
      "path": "Garage / Toolbox",
      "thumb_url": "https://stockroom.example/storage/item-images/9/thumb.webp"
    }
  ]
}
```

### `GET /home-assistant-links`

Every item that currently has a Home Assistant link, each with the **full**
`home_assistant_link` embedded — one call instead of `GET /items?has_ha_link=1`
followed by a per-item `GET /items/{id}` (N+1). Built for the integration's
Repair feature. Each element is the same `ItemResource` as `GET /items/{id}`, so
the item and link shapes are identical.

Query parameters (all optional):

| Param | Type | Effect |
| ----- | ---- | ------ |
| `instance_id` | string | Only links whose `instance_id` equals this — lets one HA instance fetch just its own links. |
| `per_page` | int | Page size (default 50, max 100). |
| `page` | int | Page number. |

```bash
curl -s "https://stockroom.example/api/v1/home-assistant-links?instance_id=5b1e7c2a-…" \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "data": [
    {
      "id": 42,
      "name": "Cordless Drill",
      "type": { "value": "item", "label": "Item" },
      "location_path": "Garage / Tool Cabinet",
      "home_assistant_link": {
        "ha_entity_id": "sensor.cordless_drill_battery",
        "ha_device_id": "9f8c0a3b1d2e4f50",
        "friendly_name": "Cordless Drill",
        "url": "https://ha.example/config/devices/device/9f8c0a3b1d2e4f50",
        "instance_id": "5b1e7c2a-…",
        "created_at": "2026-06-01T10:00:00+00:00",
        "updated_at": "2026-06-02T12:00:00+00:00"
      }
    }
  ],
  "links": { "first": "…", "last": "…", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 50, "total": 1, "last_page": 1 }
}
```

### `GET /items/{item}/maintenance-tasks`

The maintenance schedules on one item, soonest due first. Includes archived
one-offs (flagged `is_active: false`). Household-wide overdue/due-soon counts
live on `GET /statistics` (`maintenance.*`).

```json
{
  "data": [
    {
      "id": 7,
      "item_id": 42,
      "title": "Descale",
      "description": null,
      "schedule_type": "interval",
      "interval_value": 3,
      "interval_unit": "months",
      "next_due_at": "2026-09-01",
      "last_completed_at": "2026-06-01",
      "reminder_lead_days": 7,
      "is_active": true,
      "due_in_days": -4,
      "is_overdue": true,
      "needs_attention": true,
      "created_at": "2026-01-01T10:00:00+00:00",
      "updated_at": "2026-06-01T10:00:00+00:00"
    }
  ]
}
```

`due_in_days` is negative when overdue, `0` when due today, `null` for an
archived one-off. `schedule_type` is `interval`, `calendar`, or `one_off`;
`interval_value`/`interval_unit` are populated for `interval` only.

### `POST /items`

Create an item (e.g. Home Assistant auto-creating one for a device). Goes through
the same write path as the web UI — tag sync and search indexing happen
automatically. Returns the created `ItemResource` with `201 Created`.

| Field | Rules |
| ----- | ----- |
| `name` | required, string |
| `type` | required, `room`\|`container`\|`item` |
| `parent_id` | optional, must exist |
| `description`, `icon` | optional |
| `quantity` | optional int (defaults to 1) |
| `manufacturer`, `model_number`, `serial_number`, `purchased_from` | optional |
| `purchase_date`, `purchase_price`, warranty/sale fields | optional |
| `tags` | optional array of tag ids |

```bash
curl -s -X POST https://stockroom.example/api/v1/items \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"name":"Robot Vacuum","type":"item","parent_id":1,"manufacturer":"iRobot"}'
```

### `PATCH /items/{item}`

Partial update — send only the fields you want to change. `name`/`type` are
validated only when present. **Tags**: include the `tags` key to replace the
item's tags; omit it to leave them untouched.

The device's **`battery_type`** (`"AA"`, `"CR2032"`, `"AA ×4"`, …) is a plain
item field set here — a free string, so any cell can be recorded. It's a fixed
property of the device; the per-battery *level* history goes through the battery
endpoints below.

```bash
curl -s -X PATCH https://stockroom.example/api/v1/items/42 \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"quantity":3,"battery_type":"CR2032"}'
```

### `PUT /items/{item}/home-assistant-link`

Sets or replaces the item's Home Assistant link. The relationship is strictly
**1:1** — one link per item — so this is idempotent: calling it again updates the
same link rather than creating a second. Returns `201 Created` the first time and
`200 OK` when replacing.

Linking also auto-assigns a **`HomeAssistant`** tag to the item (created on first
use and recorded as the household's selected Home Assistant tag). `DELETE`
removes that tag from the item again. The item's other tags are never touched.

A link must identify its target by an **entity id or a device id** — an item
often maps to a whole device — so at least one of the two is required.

| Field | Rules |
| ----- | ----- |
| `ha_entity_id` | string (e.g. `sensor.living_room_tv`). Required unless `ha_device_id` is given. |
| `ha_device_id` | string — HA device id. Required unless `ha_entity_id` is given. |
| `friendly_name` | optional, string |
| `url` | optional, valid URL — deep link to the HA device page |
| `instance_id` | optional, string — discriminator for multi-instance setups |

```bash
curl -s -X PUT https://stockroom.example/api/v1/items/42/home-assistant-link \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"ha_entity_id":"sensor.drill_battery","url":"http://homeassistant.local:8123/config/devices/device/abc123"}'
```

```json
{ "data": { "ha_entity_id": "sensor.drill_battery", "url": "http://homeassistant.local:8123/...", "ha_device_id": null, "friendly_name": null, "instance_id": null, "created_at": "...", "updated_at": "..." } }
```

### `DELETE /items/{item}/home-assistant-link`

Removes the link. Returns `204 No Content`.

```bash
curl -s -X DELETE https://stockroom.example/api/v1/items/42/home-assistant-link \
  -H "Authorization: Bearer $TOKEN"
```

### `POST /items/{item}/maintenance-tasks`

Create a maintenance reminder on an item. **Interval** (repeats N units after
each completion) or **one-off** (a single due date that archives itself when
done) — fixed-calendar (RRULE) schedules are created in the web UI only.
`next_due_at` for an interval task is derived from today; a one-off keeps the
date you send. Returns the created `MaintenanceTaskResource` with `201 Created`.

| Field | Rules |
| ----- | ----- |
| `title` | required, string, max 255 |
| `schedule_type` | required, `interval`\|`one_off` |
| `interval_value` | required if `interval`, int 1–999 |
| `interval_unit` | required if `interval`, `days`\|`weeks`\|`months`\|`years` |
| `next_due_at` | required if `one_off`, date (`YYYY-MM-DD`) |
| `description` | optional, string |
| `reminder_lead_days` | optional int 0–365 (default 7) |

```bash
curl -s -X POST https://stockroom.example/api/v1/items/42/maintenance-tasks \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"title":"Descale","schedule_type":"interval","interval_value":3,"interval_unit":"months"}'
```

### `POST /maintenance-tasks/{task}/complete`

Mark a task done: records a history entry (performed by the token's user) and
rolls the schedule forward — interval tasks re-base on the completion date,
one-offs archive themselves. Returns the updated `MaintenanceTaskResource`.
Completing an already-archived task is a `422`.

| Field | Rules |
| ----- | ----- |
| `completed_at` | optional, date, not in the future (defaults to today) |
| `notes` | optional, string |
| `cost` | optional, numeric ≥ 0 |

```bash
curl -s -X POST https://stockroom.example/api/v1/maintenance-tasks/7/complete \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"cost":4.50,"notes":"Used citric acid."}'
```

Completing an item's **"Replace battery"** task (the system-managed forecast
reminder) is treated as a battery change: it closes the current battery cycle
and opens a fresh one, same as `POST /items/{item}/battery-changes`.

## Battery tracking

An item becomes **battery-tracked** the first time it receives a level reading.
Stockroom keeps one *cycle* per physical battery (the open cycle is the current
one; closed cycles are history) and a compressed time-series of level readings
per cycle. From that history it fits a depletion line — pooled with the last few
batteries so a fresh one is forecast from how its predecessors drained — and
projects the date the level will cross the low threshold. That date drives a
system-managed **"Replace battery"** maintenance reminder, so the dashboard
card, the daily digest and the maintenance API all light up automatically.

Set the battery **type** with `PATCH /items/{item}` (`battery_type`).

### `GET /items/{item}/battery`

Current state and the live forecast. `tracked` is `false` (and most fields
`null`) for an item that has never reported a level. `projection` is `null` until
there are enough readings to fit a draining line (≥3 samples, pooled across
recent cycles); `confidence` is the fit's R² (0–1). `reminder` mirrors the
"Replace battery" task — `next_due_at` is the predicted-low date.

```bash
curl -s https://stockroom.example/api/v1/items/42/battery \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "data": {
    "tracked": true,
    "battery_type": "CR2032",
    "current_percent": 60,
    "last_reading_at": "2026-06-10T08:00:00+00:00",
    "is_low": false,
    "installed_at": "2026-05-21T08:00:00+00:00",
    "projection": {
      "rate_per_day": -2.0,
      "predicted_low_at": "2026-06-30",
      "predicted_empty_at": "2026-07-10",
      "confidence": 1.0,
      "sample_count": 3
    },
    "reminder": { "next_due_at": "2026-06-30", "is_overdue": false, "reminder_lead_days": 3 }
  }
}
```

### `POST /items/{item}/battery-readings`

Push a level sample — the main Home Assistant path (an automation forwarding a
device's `battery_level`). Repeated identical values are compressed, and a jump
from low back up to full is auto-detected as a battery change (closing the old
cycle, opening a new one). Returns the same payload as `GET .../battery` with
`201 Created`.

| Field | Rules |
| ----- | ----- |
| `percent` | required, int 0–100 |
| `recorded_at` | optional, date-time (defaults to now) |

```bash
curl -s -X POST https://stockroom.example/api/v1/items/42/battery-readings \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"percent":58}'
```

### `POST /items/{item}/battery-changes`

Record an explicit battery swap (a button or automation, when the change isn't
inferred from a level jump). Closes the current cycle, opens a fresh one and
completes the "Replace battery" reminder. Returns the same payload as
`GET .../battery` with `201 Created`.

| Field | Rules |
| ----- | ----- |
| `changed_at` | optional, date, not in the future (defaults to now) |
| `notes` | optional, string, max 2000 |

```bash
curl -s -X POST https://stockroom.example/api/v1/items/42/battery-changes \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"notes":"Fresh CR2032."}'
```
