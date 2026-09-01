#!/bin/sh
#
# One image, three runtime processes. The container command selects which:
#
#   web       the HTTP API (stdout: JSON app logs; stderr: server lifecycle)
#   worker    the queue worker (database driver — this system runs no broker)
#   scheduler the cron-equivalent long-running scheduler
#
# Keeping them in one image means all three always run identical code; keeping
# them as separate *services* means they scale and fail independently.
set -eu

MODE="${1:-${APP_PROCESS:-web}}"

# Everything below runs as PID 1's child via exec, so signals (SIGTERM from
# `docker stop`, from Kubernetes, from Compose) reach the PHP process directly
# and the queue worker can finish its current job before exiting.

# All diagnostics in this script go to stderr, keeping the container's stdout a
# clean stream of one JSON log object per line.
wait_for_database() {
    tries=0
    until php artisan db:show --quiet >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "entrypoint: database did not become reachable after 60 attempts" >&2
            exit 1
        fi
        echo "entrypoint: waiting for the database ($tries/60)..." >&2
        sleep 2
    done
}

case "$MODE" in
    web)
        wait_for_database
        # Migrations run from the web process only, so the three services do not
        # race each other to apply the same migration on a cold start.
        # Output goes to stderr: stdout is reserved for the JSON log stream.
        php artisan migrate --force >&2
        if [ "${APP_SERVER:-builtin}" = "fpm" ]; then
            # Production shape: php-fpm behind a reverse proxy.
            exec php-fpm --nodaemonize
        fi
        # Local shape. `php -S` rather than `artisan serve` so the dev server's
        # access log stays on stderr and stdout carries nothing but the JSON
        # application log. See docker/router.php.
        exec php -S "0.0.0.0:${PORT:-8000}" -t public docker/router.php
        ;;
    worker)
        wait_for_database
        # -q silences Artisan's per-job console lines, which would otherwise
        # interleave non-JSON with the log stream on stdout. It does NOT silence
        # Monolog: the json channel writes to php://stdout directly, outside
        # Symfony Console, so failures still surface as structured log lines.
        exec php artisan queue:work --queue=default --tries=3 --backoff=5,15,60 -q
        ;;
    scheduler)
        wait_for_database
        exec php artisan schedule:work -q
        ;;
    *)
        echo "entrypoint: unknown mode '$MODE' (expected web, worker or scheduler)" >&2
        exit 64
        ;;
esac
