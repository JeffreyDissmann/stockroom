# Home Assistant integration

Stockroom pairs with [Home Assistant](https://www.home-assistant.io/) through a
custom integration (`ha-stockroom`, distributed via HACS). It surfaces your
inventory in Home Assistant and links smart-home devices to the Stockroom items
that represent them — so an HA device can deep-link to its item, and the
Stockroom item carries a back-link to its HA device page.

This page is the operator-facing setup. The integration talks to Stockroom's
[REST API](./api.md) — that page is the contract if you want the details.

## What you get

| Direction | Surface |
| --------- | ------- |
| Stockroom → Home Assistant | Statistics sensors (total items, total value, counts by type/tag/room) on an HA dashboard. |
| Home Assistant → Stockroom | A device can be linked 1:1 to a Stockroom item. The integration writes a back-link (entity id, device id, friendly name, and a deep link to the HA device page) onto the item. |
| Lookup | A "where is X?" search service that returns an item's location path. |
| Rooms ↔ Areas | Home Assistant areas map onto Stockroom **rooms**; items can be filtered by room. |
| Auto-create | A device with no matching item can have a Stockroom item created for it. |

## Prerequisites

- A reachable Stockroom instance, and its URL reachable **from Home Assistant**.
- A Stockroom **API token** (see below). Use a token with the **write** ability
  if you want device linking / auto-create; **read** alone is enough for the
  statistics sensors and lookup.
- Home Assistant with [HACS](https://hacs.xyz/) installed.

## Quick start

1. **Create an API token in Stockroom.** Open **Settings → API tokens**, name it
   `Home Assistant`, tick **Read** (and **Write** if you want linking), create it,
   and copy the token — it's shown only once.

2. **Install the integration.** Add the `ha-stockroom` repository in HACS, install
   it, and restart Home Assistant.

3. **Configure it.** Add the *Stockroom* integration in Home Assistant and enter:
   - **Host** — your Stockroom URL (e.g. `https://stockroom.example`).
   - **Token** — the token you copied.

   The integration validates the token against `GET /api/v1/user` and shows the
   connected account.

4. **Link a device.** Use the integration's link action to map a Home Assistant
   device to a Stockroom item. The item then shows a back-link to the device's
   page in Home Assistant; the device exposes the linked Stockroom item id/URL.

## How linking works

Each Stockroom item can be linked to exactly one Home Assistant entity (and vice
versa). The link is stored on the Stockroom side via
`PUT /api/v1/items/{item}/home-assistant-link` and removed with the matching
`DELETE`. Because the relationship is 1:1 and idempotent, re-linking simply
updates the existing link.

Rooms are Stockroom items of type **room**; the integration lists them via
`GET /api/v1/rooms` and can filter items by room (`GET /api/v1/items?room=…`) so
an HA area lines up with the right slice of inventory.

## Troubleshooting

- **401 on setup** — the token is wrong, revoked, or not pasted in full. Mint a new one.
- **403 when linking or creating items** — the token is read-only. Create a token with the **write** ability.
- **Host unreachable from Home Assistant** — Stockroom's URL must resolve from the
  HA host, not just your browser. A LAN IP is fine; `localhost` is not.

See [`docs/api.md`](./api.md) for the full endpoint reference.
