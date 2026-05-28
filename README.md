# Stockroom

A self-hosted home inventory: track every item, container, and room in your
household, search it (keyword + semantic), and ask a local AI assistant
about it.

> **Status:** alpha. The data model is stable and the test suite is green,
> but this is a one-person side project — expect rough edges and read the
> [CHANGELOG](./CHANGELOG.md) before upgrading.

## Features

- **Hierarchical inventory** — items, containers, and rooms in one recursive
  tree. Move anything anywhere; ancestors and breadcrumbs follow.
- **Multi-user households** — invite-only registration via single-use token
  links. Admins and members share one household.
- **Custom fields** — household-wide field definitions (text, number, date,
  boolean, URL) per item; searchable when flagged.
- **Tags** — coloured labels with item counts.
- **Images** — multiple images per item, drag-to-reorder, optional
  "search for an image" via [Brave Search](https://brave.com/search/api/).
- **Hybrid search** — Meilisearch keyword search plus app-side vector
  embeddings (works with local Ollama or any provider the Laravel AI SDK
  supports).
- **AI assistant** — multi-turn chat with read/write tools that can find,
  create, update, move, tag and delete items. Backed by a local Ollama
  endpoint that *you* control; nothing leaves your network unless you point
  it elsewhere.
- **Activity log** — every change is attributed to a user.
- **Localization** — English and German; per-user locale.
- **Backup / restore** — a single archive of the household.

## Self-host with Docker

You need [Docker](https://www.docker.com/) (Compose v2). Everything else
runs in containers.

```bash
# 1. Get the compose file and the example env
curl -O https://raw.githubusercontent.com/JeffreyDissmann/stockroom/main/docker-compose.prod.yml
curl -o .env https://raw.githubusercontent.com/JeffreyDissmann/stockroom/main/.env.docker.example

# 2. Edit .env — at minimum set APP_URL, STOCKROOM_ADMIN_EMAIL,
#    STOCKROOM_ADMIN_PASSWORD, and the DB / Meilisearch keys.
$EDITOR .env

# 3. Start it
docker compose -f docker-compose.prod.yml up -d
```

The app generates an `APP_KEY` on first boot if one isn't set, runs
migrations, links storage, and creates the first admin from
`STOCKROOM_ADMIN_EMAIL` + `STOCKROOM_ADMIN_PASSWORD` (only if no users
exist — change those env vars after first boot, they become inert).

Open `http://<your-host>:8080` (or whatever you put behind your reverse
proxy). The image expects to sit behind HTTPS in any real deployment;
put it behind [Caddy](https://caddyserver.com/), Traefik, or nginx.

### Persistence

Three volumes hold state — back these up:

- `db` — Postgres data.
- `meili` — Meilisearch index (rebuildable from the DB with
  `scout:import`, but slow).
- `app-storage` — item images and other user uploads. **Loss of this
  volume = loss of all uploaded images.**

### Optional: AI assistant

The AI assistant needs an external Ollama (or any provider the Laravel AI
SDK supports). Stockroom itself works without it — set `AI_ENABLED=false`
to hide the chat surface entirely.

To enable it, point `OLLAMA_URL` at an Ollama endpoint you operate
(another container, a LAN machine, a separate GPU box):

```env
AI_ENABLED=true
AI_PROVIDER=ollama
OLLAMA_URL=http://192.168.x.x:11434
AI_CHAT_MODEL=ministral-3:8b      # any tool-calling model from `ollama list`
AI_EMBEDDINGS_MODEL=bge-m3:567m
AI_EMBEDDINGS_DIMENSIONS=768
```

Pull the models on the Ollama host:

```bash
ollama pull ministral-3:8b
ollama pull bge-m3:567m
```

The Stockroom image deliberately does **not** bundle Ollama — model
weights are multi-gigabyte and most self-hosters either already run
Ollama or don't want it at all.

### Optional: image search

Set `BRAVE_SEARCH_KEY` to a [Brave Search Image API](https://brave.com/search/api/)
key to enable the "search for an image" button on items. Leave blank to
disable.

## Configuration reference

| Variable | Default | Notes |
|---|---|---|
| `APP_URL` | `http://localhost` | Public URL (include scheme + port if non-standard). |
| `APP_LOCALE` | `en` | Default UI language; users can override per-account. |
| `CURRENCY` | `EUR` | ISO 4217 code; applied household-wide. |
| `CURRENCY_LOCALE` | `de-DE` | Formatting locale (e.g. `en-US`). |
| `STOCKROOM_ADMIN_EMAIL` | — | First-boot admin seed; ignored once users exist. |
| `STOCKROOM_ADMIN_PASSWORD` | — | First-boot admin seed; ignored once users exist. |
| `DB_*` | Postgres in compose | Standard Laravel DB env. |
| `SCOUT_DRIVER` | `meilisearch` | Set blank to fall back to in-process search (not recommended). |
| `MEILISEARCH_HOST` | — | Required when scout driver is `meilisearch`. |
| `MEILISEARCH_KEY` | — | Required when scout driver is `meilisearch`. |
| `AI_ENABLED` | `true` | Master switch for the assistant; set `false` to hide every AI surface. |
| `OLLAMA_URL` | — | URL of an Ollama endpoint you operate. |
| `AI_CHAT_MODEL` | `ministral-3:8b` | Must support tool calling. |
| `AI_CHAT_RETENTION_DAYS` | `3` | Older conversations are deleted daily. |
| `BRAVE_SEARCH_KEY` | — | Enables image search; blank disables it. |

The complete list with comments lives in [`.env.example`](./.env.example).

## Develop locally (Sail)

The dev stack is Laravel Sail (Docker), Vite, Pest.

```bash
git clone https://github.com/JeffreyDissmann/stockroom.git
cd stockroom
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail npm install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run dev
```

Open `http://localhost`. Tests:

```bash
./vendor/bin/sail artisan test --compact          # unit + feature
./vendor/bin/sail bash -c \
  "PLAYWRIGHT_BROWSERS_PATH=/home/sail/pw-browsers ./vendor/bin/pest tests/Browser"
```

Lint / format:

```bash
./vendor/bin/sail bin pint                # PHP
./vendor/bin/sail npm run lint            # ESLint
./vendor/bin/sail npm run wayfinder:check # typed-route drift
```

## Tech stack

PHP 8.5 · Laravel 13 · Inertia v2 · Vue 3 · TypeScript · Tailwind v3 ·
Postgres · Meilisearch · Pest · FrankenPHP runtime · Laravel Wayfinder
(typed routes) · Laravel AI SDK.

## Acknowledgements

Stockroom owes a lot to **[HomeBox](https://github.com/sysadminsmedia/homebox)**.
The hierarchical inventory model, the "track everything in your house"
framing, and several UI patterns are directly inspired by it. If
Stockroom's stack (Laravel / Inertia / Vue) isn't for you, HomeBox
(Go + Vue) is excellent and worth a look. Thank you to its maintainers
and contributors.

## License

[MIT](./LICENSE) © 2026 Jeffrey Dissmann
