#!/usr/bin/env sh
# Stockroom container entrypoint.
#
# STOCKROOM_ROLE selects what this container does:
#   web        — run first-boot housekeeping, then FrankenPHP (default)
#   queue      — wait for the DB, then `php artisan queue:work`
#   scheduler  — wait for the DB, then `php artisan schedule:work`
#
# Anything else is exec'd verbatim, so `docker compose run app sh` still works.

set -e

cd /app

# ---------------------------------------------------------------------------
# Shared helpers
# ---------------------------------------------------------------------------

wait_for_db() {
    # Block until artisan can reach the configured DB. Cheap-ish: it loads the
    # framework once, but it's the most reliable way that respects the app's
    # own DB config (driver, host, port, auth).
    tries=60
    until php artisan db:show --counts >/dev/null 2>&1; do
        tries=$((tries - 1))
        if [ "$tries" -le 0 ]; then
            echo "stockroom: database not reachable after 60 attempts, giving up" >&2
            exit 1
        fi
        echo "stockroom: waiting for database…"
        sleep 2
    done
}

# ---------------------------------------------------------------------------
# First-boot housekeeping (web role only — workers wait for it to finish)
# ---------------------------------------------------------------------------

if [ "${STOCKROOM_ROLE:-web}" = "web" ]; then
    # APP_KEY handling. Three things make this finicky:
    #
    #   1. Laravel's Dotenv treats OS env vars as immutable, so any APP_KEY
    #      injected by `environment:` / `env_file:` in compose wins — even if
    #      it's the empty string. That's the most common footgun on first
    #      boot: an empty APP_KEY= line in .env.docker.example overrides any
    #      key we generate, and the app 500s on every request.
    #   2. `php artisan key:generate` writes to .env but can only REPLACE an
    #      existing APP_KEY=… line; an empty .env makes it error out.
    #   3. /app is image-resident, not volume-mounted, so .env doesn't survive
    #      container recreations. We persist the key under storage/ instead,
    #      which IS mounted, so the same key is reused across restarts and
    #      sessions / encrypted columns stay valid.
    KEY_FILE=/app/storage/app/.stockroom-app-key

    if [ -z "${APP_KEY:-}" ]; then
        if [ -s "$KEY_FILE" ]; then
            APP_KEY="$(cat "$KEY_FILE")"
            export APP_KEY
            echo "stockroom: loaded persisted APP_KEY from $KEY_FILE"
        else
            APP_KEY="$(php artisan key:generate --show --no-interaction)"
            export APP_KEY
            mkdir -p "$(dirname "$KEY_FILE")"
            printf '%s' "$APP_KEY" > "$KEY_FILE"
            chmod 600 "$KEY_FILE"
            echo "stockroom: generated APP_KEY and persisted to $KEY_FILE"
        fi
    fi

    wait_for_db

    echo "stockroom: running migrations"
    php artisan migrate --force --no-interaction

    # storage:link is idempotent — it skips if the symlink already exists and
    # points at the right target. Safe to run on every boot.
    php artisan storage:link --force >/dev/null 2>&1 || true

    # Push search index settings (filterableAttributes / sortableAttributes /
    # the userProvided embedder) to Meilisearch. Idempotent — Scout creates
    # the index if it doesn't exist yet, otherwise merges settings. Boot
    # shouldn't fail if Meili happens to be slow to come up; the picker
    # endpoints would return 5xx briefly until the next container restart
    # but the rest of the app keeps working.
    if [ "${SCOUT_DRIVER:-meilisearch}" = "meilisearch" ]; then
        echo "stockroom: syncing search index settings to Meilisearch"
        php artisan scout:sync-index-settings --no-interaction || echo "stockroom: WARNING — scout:sync-index-settings failed; pickers may return 5xx until next boot" >&2
    fi

    # Optional first-admin seed. Driven by STOCKROOM_ADMIN_EMAIL +
    # STOCKROOM_ADMIN_PASSWORD; the command itself is idempotent and no-ops
    # once any user exists.
    if [ -n "${STOCKROOM_ADMIN_EMAIL:-}" ] && [ -n "${STOCKROOM_ADMIN_PASSWORD:-}" ]; then
        php artisan stockroom:install --no-interaction || true
    fi

    # Production caches. Build them late so the migration & install commands
    # above see fresh config.
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    echo "stockroom: boot complete, starting FrankenPHP"
    exec frankenphp run --config /etc/caddy/Caddyfile
fi

# ---------------------------------------------------------------------------
# Worker roles
# ---------------------------------------------------------------------------

case "${STOCKROOM_ROLE}" in
    queue)
        wait_for_db
        exec php artisan queue:work --tries=3 --backoff=5 --timeout=120
        ;;
    scheduler)
        wait_for_db
        exec php artisan schedule:work
        ;;
    *)
        # Unknown role → exec whatever was passed (lets you `docker run … sh`
        # or run one-off artisan commands).
        exec "$@"
        ;;
esac
