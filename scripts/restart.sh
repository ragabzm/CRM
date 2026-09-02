#!/usr/bin/env bash
#
# Rebuild the images and bring the whole stack back up, in Docker.
#
#   ./scripts/restart.sh                  # build, restart, migrate:fresh --seed
#   ./scripts/restart.sh --no-fresh       # keep the data
#   ./scripts/restart.sh --no-seed        # wipe, but do not seed
#   ./scripts/restart.sh --no-build       # reuse the existing images
#   ./scripts/restart.sh --wipe-volume    # also delete the pgdata volume
#   ./scripts/restart.sh --stop           # stop everything and exit
#   ./scripts/restart.sh --logs           # follow the logs of a running stack
#   ./scripts/restart.sh --yes            # skip the "this drops your data" prompt
#
# Everything runs in Docker: five services out of docker-compose.yml. The
# frontend build happens inside `docker compose build` — the Dockerfile's
# builder stage runs `pnpm run build`, so there is no separate build step here
# and nothing is compiled on the host.
#
# `migrate:fresh` DROPS EVERY TABLE. That is the point of the script, but it is
# also irreversible, so it names the database and counts the tables before
# asking. --yes is for automation, not for saving two seconds.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE=(docker compose -f "${ROOT}/docker-compose.yml")
RUN_DIR="${ROOT}/.run"
BACKEND_PORT=8000
FRONTEND_PORT=3000

DO_FRESH=1
DO_SEED=1
DO_BUILD=1
WIPE_VOLUME=0
ASSUME_YES=0
ACTION="restart"

for arg in "$@"; do
  case "${arg}" in
    --no-fresh)    DO_FRESH=0 ;;
    --no-seed)     DO_SEED=0 ;;
    --no-build)    DO_BUILD=0 ;;
    --wipe-volume) WIPE_VOLUME=1 ;;
    --stop)        ACTION="stop" ;;
    --logs)        ACTION="logs" ;;
    --yes|-y)      ASSUME_YES=1 ;;
    -h|--help)     sed -n '2,22p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) printf 'Unknown option: %s (try --help)\n' "${arg}" >&2; exit 2 ;;
  esac
done

mkdir -p "${RUN_DIR}"

say()  { printf '\n\033[1m▸ %s\033[0m\n' "$*"; }
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
die()  { printf '\n\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# --logs is just a passthrough, and it must work without any of the checks
# below — the whole point is to look at a stack that is already misbehaving.
# ---------------------------------------------------------------------------
if [ "${ACTION}" = "logs" ]; then
  exec "${COMPOSE[@]}" logs -f --tail=100
fi

# ---------------------------------------------------------------------------
# Preflight
# ---------------------------------------------------------------------------
say "Preflight"

docker version >/dev/null 2>&1 || die "the Docker daemon is not running — start Docker Desktop first"
ok "docker $(docker version --format '{{.Server.Version}}' 2>/dev/null)"

# Something on the host holding 8000 or 3000 makes the port bind fail with a
# message that blames Compose rather than the process actually squatting on it.
# Named here, while the name is still available.
#
# One lsof call PER PORT. `lsof -iTCP:8000,TCP:3000` looks like it should work
# and does not — lsof rejects it with "unknown service TCP:3000" and exits
# non-zero, so the check silently found nothing and the bind failed anyway.
#
# Docker's own listeners are skipped: those ARE the stack, and `down` handles
# them. (This comment sits outside the command substitution on purpose — an
# apostrophe inside $( ) derails the bash parser.)
find_squatters() {
  for port in "${BACKEND_PORT}" "${FRONTEND_PORT}"; do
    lsof -nP -tiTCP:"${port}" -sTCP:LISTEN 2>/dev/null
  done | sort -u | while read -r pid; do
    ps -o comm= -p "${pid}" 2>/dev/null | grep -qi docker || echo "${pid}"
  done
}

clear_squatters() {
  local squatters
  squatters="$(find_squatters)"
  [ -z "${squatters}" ] && return 0

  warn "these host processes hold ${BACKEND_PORT}/${FRONTEND_PORT} and will block the container ports:"
  for pid in ${squatters}; do
    printf '      %s  %s\n' "${pid}" "$(ps -o command= -p "${pid}" 2>/dev/null | cut -c1-90)"
  done

  local reply
  if [ "${ASSUME_YES}" -eq 1 ]; then
    reply="y"
  else
    printf '  Stop them? [y/N] '
    read -r reply </dev/tty
  fi

  case "${reply}" in
    y|Y|yes|YES)
      echo "${squatters}" | xargs kill 2>/dev/null
      sleep 2
      for pid in ${squatters}; do kill -0 "${pid}" 2>/dev/null && kill -9 "${pid}" 2>/dev/null; done
      ok "host processes stopped"
      ;;
    *) die "ports are in use; nothing was changed" ;;
  esac
}

clear_squatters

# ---------------------------------------------------------------------------
# Count what is about to be destroyed
#
# Asked of Postgres directly rather than through Artisan: the backend container
# may not be running yet, and this has to work before anything is started.
# ---------------------------------------------------------------------------
table_count() {
  "${COMPOSE[@]}" exec -T postgres \
    psql -U ragab -d ragab -tAc \
    "select count(*) from information_schema.tables where table_schema='public'" 2>/dev/null \
    | tr -d '[:space:]'
}

# Postgres is started if it is down, purely so the confirmation prompt can name
# a real number. Starting one container is cheap and changes nothing; a prompt
# that says "all ? tables" is a prompt people learn to click through.
if ! "${COMPOSE[@]}" ps --status running --services 2>/dev/null | grep -qx postgres; then
  "${COMPOSE[@]}" up -d postgres >/dev/null 2>&1
  tries=30
  until [ "$("${COMPOSE[@]}" ps postgres --format '{{.Health}}' 2>/dev/null)" = "healthy" ]; do
    tries=$((tries - 1)); [ "${tries}" -le 0 ] && break
    sleep 1
  done
fi

TABLES="$(table_count)"
ok "database ragab has ${TABLES:-?} tables right now"

# ---------------------------------------------------------------------------
# Confirm the destructive step
# ---------------------------------------------------------------------------
if [ "${ACTION}" = "restart" ] && [ "${ASSUME_YES}" -eq 0 ]; then
  if [ "${WIPE_VOLUME}" -eq 1 ]; then
    printf '\n\033[33m--wipe-volume DELETES the pgdata volume. The database and every byte\n'
    printf 'in it go away, including anything not created by a migration.\033[0m\n'
    printf 'Continue? [y/N] '
    read -r reply </dev/tty
    case "${reply}" in y|Y|yes|YES) ;; *) printf 'Aborted.\n'; exit 0 ;; esac
  elif [ "${DO_FRESH}" -eq 1 ]; then
    printf '\n\033[33mmigrate:fresh will DROP all %s tables in "ragab" and rebuild them empty.\033[0m\n' \
      "${TABLES:-?}"
    printf 'This cannot be undone. Continue? [y/N] '
    read -r reply </dev/tty
    case "${reply}" in
      y|Y|yes|YES) ;;
      *) printf 'Aborted. (Use --no-fresh to rebuild without touching the data.)\n'; exit 0 ;;
    esac
  fi
fi

# ---------------------------------------------------------------------------
# Stop
# ---------------------------------------------------------------------------
say "Stopping the stack"
if [ "${WIPE_VOLUME}" -eq 1 ]; then
  "${COMPOSE[@]}" down -v --remove-orphans
  ok "containers and the pgdata volume removed"
else
  "${COMPOSE[@]}" down --remove-orphans
  ok "containers stopped (pgdata volume kept)"
fi

if [ "${ACTION}" = "stop" ]; then
  printf '\n\033[1mStopped.\033[0m\n'
  exit 0
fi

# ---------------------------------------------------------------------------
# Build
#
# This is where the frontend is built: the Dockerfile's builder stage runs
# `node scripts/check-next-version.mjs && pnpm run build`, so a version-guard
# failure or a type error stops the release here rather than at runtime.
# ---------------------------------------------------------------------------
if [ "${DO_BUILD}" -eq 1 ]; then
  say "Building images (this is where the frontend build runs)"
  # PIPESTATUS[0], not $?: the exit status wanted is the build's, and the one
  # $? carries is grep's. Building twice to recover it would repeat several
  # minutes of work.
  "${COMPOSE[@]}" build 2>&1 | tee "${RUN_DIR}/build.log" \
    | grep -E "^#[0-9]+ (DONE|ERROR)|naming to |writing image" | tail -8
  build_status="${PIPESTATUS[0]}"

  if [ "${build_status}" -ne 0 ]; then
    tail -40 "${RUN_DIR}/build.log"
    die "image build failed — see ${RUN_DIR}/build.log"
  fi
  ok "images built"
else
  say "Skipping the build (--no-build)"
fi

# ---------------------------------------------------------------------------
# Start
# ---------------------------------------------------------------------------
say "Starting the stack"

# Checked again, not only at preflight. `down`, the image build and the pull all
# happen in between — minutes during which another terminal (or another agent)
# can start a dev server on 3000. Finding it here costs a second; finding it
# from the daemon costs the whole run.
clear_squatters

"${COMPOSE[@]}" up -d --remove-orphans || die "compose up failed"

# postgres is the only service with a healthcheck; the rest just have to be
# running before anything is asked of them.
printf '  waiting for postgres'
tries=60
until [ "$("${COMPOSE[@]}" ps postgres --format '{{.Health}}' 2>/dev/null)" = "healthy" ]; do
  tries=$((tries - 1)); [ "${tries}" -le 0 ] && { printf '\n'; die "postgres never became healthy"; }
  printf '.'; sleep 1
done
printf '\n'; ok "postgres healthy"

# The web container runs `migrate --force` from its entrypoint before it serves
# anything, so it is up only once the schema is in place.
printf '  waiting for backend-web'
tries=90
until curl -fsS -o /dev/null "http://127.0.0.1:${BACKEND_PORT}/up" 2>/dev/null \
   || curl -fsS -o /dev/null "http://127.0.0.1:${BACKEND_PORT}/" 2>/dev/null; do
  tries=$((tries - 1))
  if [ "${tries}" -le 0 ]; then
    printf '\n'
    "${COMPOSE[@]}" logs --tail=30 backend-web
    die "backend-web never answered on ${BACKEND_PORT}"
  fi
  printf '.'; sleep 1
done
printf '\n'; ok "backend-web answering on ${BACKEND_PORT}"

# ---------------------------------------------------------------------------
# Fresh
#
# After the stack is up, not before: migrate:fresh runs inside the container,
# against the compose network, using exactly the configuration the app itself
# uses. Running it from the host would need a second, host-shaped DB_HOST.
# ---------------------------------------------------------------------------
if [ "${DO_FRESH}" -eq 1 ] && [ "${WIPE_VOLUME}" -eq 0 ]; then
  say "Backend — migrate:fresh"
  if [ "${DO_SEED}" -eq 1 ]; then
    "${COMPOSE[@]}" exec -T backend-web php artisan migrate:fresh --seed --force \
      2>&1 | tee "${RUN_DIR}/migrate.log" | tail -6
  else
    "${COMPOSE[@]}" exec -T backend-web php artisan migrate:fresh --force \
      2>&1 | tee "${RUN_DIR}/migrate.log" | tail -6
  fi
  grep -qiE "FAIL|SQLSTATE|Exception" "${RUN_DIR}/migrate.log" \
    && die "migrations failed — see ${RUN_DIR}/migrate.log"
  ok "schema rebuilt$([ "${DO_SEED}" -eq 1 ] && echo ' and seeded')"

  # The worker and the scheduler opened their connections against the old
  # schema. Restarted so neither is left holding a handle to a dropped table.
  "${COMPOSE[@]}" restart backend-worker backend-scheduler >/dev/null 2>&1
  ok "worker and scheduler restarted"
elif [ "${WIPE_VOLUME}" -eq 1 ]; then
  say "Backend — schema built from scratch by the entrypoint (volume was wiped)"
  if [ "${DO_SEED}" -eq 1 ]; then
    "${COMPOSE[@]}" exec -T backend-web php artisan db:seed --force 2>&1 | tail -4
    ok "seeded"
  fi
else
  say "Backend — skipping migrate:fresh (--no-fresh)"
fi

# ---------------------------------------------------------------------------
# Frontend readiness
# ---------------------------------------------------------------------------
printf '  waiting for frontend'
tries=90
until curl -fsS -o /dev/null "http://127.0.0.1:${FRONTEND_PORT}/" 2>/dev/null; do
  tries=$((tries - 1))
  if [ "${tries}" -le 0 ]; then
    printf '\n'
    "${COMPOSE[@]}" logs --tail=30 frontend
    die "frontend never answered on ${FRONTEND_PORT}"
  fi
  printf '.'; sleep 1
done
printf '\n'; ok "frontend answering on ${FRONTEND_PORT}"

# ---------------------------------------------------------------------------
say "Up"
"${COMPOSE[@]}" ps --format 'table {{.Service}}\t{{.Status}}\t{{.Ports}}'

printf '\n'
printf '  frontend  http://localhost:%s\n' "${FRONTEND_PORT}"
printf '  backend   http://localhost:%s/api/v1\n' "${BACKEND_PORT}"
printf '  logs      ./scripts/restart.sh --logs\n'
printf '  stop      ./scripts/restart.sh --stop\n'
