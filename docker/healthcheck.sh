#!/usr/bin/env sh
# Role-aware container healthcheck.
#
# The frankenphp base image bakes in a HEALTHCHECK that probes Caddy's admin
# endpoint (localhost:2019/metrics). Only the web role runs Caddy, so queue
# and scheduler containers inherit a probe that can never pass and sit
# "unhealthy" forever — masking real failures. This script probes what each
# role actually runs instead.
#
# Worker roles exec their artisan command as PID 1, so "is the role process
# alive" is read straight from /proc/1/cmdline (no procps needed; grep -q is
# binary-safe against the NUL-separated cmdline).

case "${STOCKROOM_ROLE:-web}" in
    web)
        exec curl -fsS http://localhost:8080/up >/dev/null
        ;;
    queue)
        grep -q "queue:work" /proc/1/cmdline
        ;;
    scheduler)
        grep -q "schedule:work" /proc/1/cmdline
        ;;
    *)
        # One-off containers (`docker run … sh`, ad-hoc artisan) have nothing
        # meaningful to probe.
        exit 0
        ;;
esac
