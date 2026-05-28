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
    # APP_KEY: generate one in-place if the operator left it blank. Persisted
    # into .env so subsequent restarts reuse it. Without this, sessions and
    # encrypted columns invalidate every time the container restarts.
    if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
        if [ ! -f .env ]; then
            touch .env
        fi
        echo "stockroom: generating APP_KEY"
        php artisan key:generate --force --no-interaction
    fi

    wait_for_db

    echo "stockroom: running migrations"
    php artisan migrate --force --no-interaction

    # storage:link is idempotent — it skips if the symlink already exists and
    # points at the right target. Safe to run on every boot.
    php artisan storage:link --force >/dev/null 2>&1 || true

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
