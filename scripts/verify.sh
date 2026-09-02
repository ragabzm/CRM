#!/usr/bin/env bash
#
# Full verification. Slow (~2-4 min), so run it in the background and read the
# log when it finishes:
#
#   ./scripts/verify.sh backend    # ~25s  — tests, deptrac, contract drift
#   ./scripts/verify.sh frontend   # ~2m   — typecheck, lint, tests, build
#   ./scripts/verify.sh            # both
#
# Every step runs even if an earlier one fails, so one run reports every
# problem rather than only the first. The exit code is non-zero if any failed.

set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="${ROOT}/.verify"
mkdir -p "${LOG_DIR}"

FAILED=()

step() {
  local name="$1"; shift
  local log="${LOG_DIR}/${name}.log"

  printf '\n=== %s ===\n' "${name}"

  if "$@" >"${log}" 2>&1; then
    printf 'PASS  %s\n' "${name}"
  else
    printf 'FAIL  %s  (see %s)\n' "${name}" "${log}"
    tail -30 "${log}"
    FAILED+=("${name}")
  fi
}

run_backend() {
  cd "${ROOT}/backend"
  step backend-tests   php artisan test
  # BOTH deptrac configs: module boundaries and tier direction are separate
  # rulesets, and running only one leaves half the architecture unchecked.
  step backend-modules vendor/bin/deptrac analyse --config-file=deptrac.yaml --no-progress --fail-on-uncovered
  step backend-tiers   vendor/bin/deptrac analyse --config-file=deptrac-tiers.yaml --no-progress --fail-on-uncovered
  step backend-imports composer no-cross-import
  step backend-openapi php artisan openapi:check
}

run_frontend() {
  cd "${ROOT}/frontend"
  step frontend-typecheck pnpm typecheck
  step frontend-lint      pnpm lint
  step frontend-tokens    pnpm run lint:tokens
  step frontend-imports   pnpm run check:no-cross-import
  step frontend-format    pnpm run format:check
  step frontend-tests     pnpm test
  step frontend-build     pnpm build
}

case "${1:-all}" in
  backend)  run_backend ;;
  frontend) run_frontend ;;
  *)        run_backend; run_frontend ;;
esac

printf '\n===============================\n'
if [ ${#FAILED[@]} -eq 0 ]; then
  printf 'ALL CHECKS PASSED\n'
  exit 0
fi

printf 'FAILED: %s\n' "${FAILED[*]}"
exit 1
