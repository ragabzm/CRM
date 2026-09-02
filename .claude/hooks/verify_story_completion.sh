#!/usr/bin/env bash
#
# .claude/hooks/verify_story_completion.sh
#
# Stop hook: fires whenever Claude tries to end its turn.
# Purpose:
#   - If the current story's plan has unchecked "Done Criteria" items,
#     BLOCK the stop and tell Claude to implement them now (no report,
#     no gaps entry — plan items just get done and checked off).
#   - Claude is separately instructed (in the injected reason) that if it
#     finds an issue NOT covered by the plan, it must log it under
#     .squad/gaps/<plan_number>-<story_id>.md instead of reporting it or
#     silently skipping it.
#   - If everything in the plan is checked off, the stop is allowed
#     (this is what lets the story's own "STOP HERE, wait for confirmation"
#     instruction actually take effect).
#
# Requires:
#   - A state file written by scripts/next_story.py when a story starts:
#       .squad/state/current_story.json
#       { "plan_path": "docs/stories/story-04-be-04.md",
#         "plan_number": "04",
#         "story_id": "451" }
#   - The plan file contains a "## Done Criteria" section with `- [ ]` /
#     `- [x]` checklist lines (exactly like the story docs already do).
#
# Fails open: any missing prerequisite (no state file, no plan file, no
# Done Criteria section) simply allows the stop — this hook should never
# be the reason a session gets stuck if the project layout hasn't been
# wired up yet.

set -uo pipefail

REPO_ROOT="${CLAUDE_PROJECT_DIR:-$(pwd)}"
STATE_DIR="${REPO_ROOT}/.claude/hooks/.state"
STOP_ITER_DIR="${STATE_DIR}/stop-iterations"
LOG_FILE="${STATE_DIR}/stop-hook.log"
MAX_ITER="${SQUAD_STOP_HOOK_MAX_ITER:-6}"

mkdir -p "${STOP_ITER_DIR}"

log() {
    echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') $*" >> "${LOG_FILE}"
}

# Read hook input (JSON on stdin) — we only need session_id.
INPUT_JSON="$(cat || true)"
SESSION_ID="$(python3 - "$INPUT_JSON" <<'PY'
import json, sys
try:
    data = json.loads(sys.argv[1] or "{}")
    print(data.get("session_id", "unknown"))
except Exception:
    print("unknown")
PY
)"

allow_stop() {
    # No output => default decision (allow). Optionally log why.
    log "ALLOW session=${SESSION_ID} reason=$1"
    exit 0
}

block_stop() {
    local reason="$1"
    python3 - "$reason" <<'PY'
import json, sys
print(json.dumps({"decision": "block", "reason": sys.argv[1]}))
PY
    log "BLOCK session=${SESSION_ID}"
    exit 0
}

# --- 1. Locate current story state (scoped to this session) -----------------
CURRENT_STORY_FILE="${REPO_ROOT}/.squad/state/current_story-${SESSION_ID}.json"
if [[ ! -f "${CURRENT_STORY_FILE}" ]]; then
    allow_stop "no current_story state for this session — nothing to verify"
fi

read -r PLAN_PATH PLAN_NUMBER STORY_ID <<PYOUT
$(python3 - "${CURRENT_STORY_FILE}" <<'PY'
import json, sys
try:
    with open(sys.argv[1], "r", encoding="utf-8") as f:
        data = json.load(f)
    print(data.get("plan_path", ""), data.get("plan_number", "00"), data.get("story_id", "000"))
except Exception:
    print("", "00", "000")
PY
)
PYOUT

if [[ -z "${PLAN_PATH}" ]]; then
    allow_stop "current_story.json missing plan_path"
fi

ABS_PLAN_PATH="${REPO_ROOT}/${PLAN_PATH}"
if [[ ! -f "${ABS_PLAN_PATH}" ]]; then
    allow_stop "plan file not found at ${ABS_PLAN_PATH}"
fi

# --- 2. Reset iteration counter if this is a new story ----------------------
ITER_FILE="${STOP_ITER_DIR}/${SESSION_ID}.json"
CURRENT_ITER=0
if [[ -f "${ITER_FILE}" ]]; then
    read -r STORED_STORY_ID STORED_ITER <<PYOUT
$(python3 - "${ITER_FILE}" <<'PY'
import json, sys
try:
    with open(sys.argv[1], "r", encoding="utf-8") as f:
        data = json.load(f)
    print(data.get("story_id", ""), data.get("iterations", 0))
except Exception:
    print("", 0)
PY
)
PYOUT
    if [[ "${STORED_STORY_ID}" == "${STORY_ID}" ]]; then
        CURRENT_ITER="${STORED_ITER}"
    fi
fi

# --- 3. Extract unchecked Done Criteria items --------------------------------
UNCHECKED="$(python3 - "${ABS_PLAN_PATH}" <<'PY'
import re, sys

with open(sys.argv[1], "r", encoding="utf-8") as f:
    text = f.read()

# Grab the "## Done Criteria" section up to the next "##" heading or EOF.
m = re.search(r"^##\s*Done Criteria\s*$(.*?)(?=^##\s|\Z)", text, re.MULTILINE | re.DOTALL)
if not m:
    sys.exit(0)  # no section => nothing to enforce

section = m.group(1)
unchecked = re.findall(r"^\s*-\s*\[\s\]\s*(.+)$", section, re.MULTILINE)
for line in unchecked:
    print(line.strip())
PY
)"

if [[ -z "${UNCHECKED}" ]]; then
    allow_stop "all Done Criteria checked off for story ${STORY_ID}"
fi

UNCHECKED_COUNT="$(echo "${UNCHECKED}" | grep -c . || true)"

# --- 4. Iteration ceiling to avoid infinite loops ---------------------------
if (( CURRENT_ITER >= MAX_ITER )); then
    GAPS_DIR="${REPO_ROOT}/.squad/gaps"
    mkdir -p "${GAPS_DIR}"
    GAPS_FILE="${GAPS_DIR}/${PLAN_NUMBER}-${STORY_ID}.md"
    {
        echo ""
        echo "## Auto-completion ceiling reached ($(date -u +'%Y-%m-%dT%H:%M:%SZ'))"
        echo "Stop hook attempted ${CURRENT_ITER} auto-continue cycles but the following"
        echo "Done Criteria are still unchecked and need manual follow-up:"
        echo ""
        echo "${UNCHECKED}" | sed 's/^/- /'
    } >> "${GAPS_FILE}"
    log "CEILING session=${SESSION_ID} story=${STORY_ID} logged_to=${GAPS_FILE}"
    allow_stop "iteration ceiling reached, logged remaining gaps for manual review"
fi

NEW_ITER=$((CURRENT_ITER + 1))
python3 - "${ITER_FILE}" "${STORY_ID}" "${NEW_ITER}" <<'PY'
import json, sys
path, story_id, iterations = sys.argv[1], sys.argv[2], int(sys.argv[3])
with open(path, "w", encoding="utf-8") as f:
    json.dump({"story_id": story_id, "iterations": iterations}, f)
PY

# --- 5. Build the block reason ----------------------------------------------
GAPS_TARGET=".squad/gaps/${PLAN_NUMBER}-${STORY_ID}.md"

read -r -d '' REASON <<EOF
Story ${STORY_ID} (plan: ${PLAN_PATH}) is not actually finished — ${UNCHECKED_COUNT} item(s)
under "## Done Criteria" are still unchecked:

${UNCHECKED}

Rules for finishing this turn:
1. Implement every unchecked item above now, directly — do NOT write a status
   report and do NOT log these in the gaps file. They are already tracked in
   the plan; just do them and mark each one "- [x]" in ${PLAN_PATH} once it's
   genuinely done (tests passing, not just stubbed).
2. If, while doing this, you discover a real issue or missing piece that is
   NOT described anywhere in ${PLAN_PATH}, do not fold it into a report either.
   Instead append it to ${GAPS_TARGET} (create the .squad/gaps/ directory and
   this file if they don't exist yet) with a short heading and description.
   Then keep going with the plan items — don't stop to ask about it.
3. Only stop again once every "## Done Criteria" checkbox in ${PLAN_PATH} is
   [x]. If the plan itself says "STOP HERE, wait for confirmation" and all
   boxes are checked, that stop is fine.
EOF

block_stop "${REASON}"
