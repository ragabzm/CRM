#!/usr/bin/env bash
#
# .claude/hooks/track_current_story.sh
#
# UserPromptSubmit hook: fires the moment the user sends a prompt, before
# Claude does anything. If the prompt references a plan file under
# .squad/plans/**/*.md (however it's phrased — "Implement X", "continue X",
# a bare path, etc.), this writes/updates:
#
#   .squad/state/current_story.json
#
# which verify_story_completion.sh (the Stop hook) relies on to know which
# plan/story is currently in flight. This means the user never has to
# maintain that file by hand or go through scripts/next_story.py — just
# mentioning the plan path in a prompt is enough.
#
# plan_number / story_id are parsed, in order of preference, from:
#   1. The filename pattern NN-story-ID.md (e.g. 04-story-451.md)
#   2. A "(Story: ID)" marker + a "Story NN" heading inside the file itself
#   3. Falls back to "00" / "000" if neither is found (Stop hook will still
#      work, just with a less meaningful gaps filename)
#
# Always exits 0 / prints nothing that would block the prompt — this hook
# only observes, it never rejects a prompt.

set -uo pipefail

REPO_ROOT="$(pwd)"
STATE_DIR="${REPO_ROOT}/.squad/state"
LOG_FILE="${REPO_ROOT}/.claude/hooks/.state/track-story.log"

mkdir -p "${STATE_DIR}" "$(dirname "${LOG_FILE}")"

log() {
    echo "$(date -u +'%Y-%m-%dT%H:%M:%SZ') $*" >> "${LOG_FILE}"
}

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

STATE_FILE="${STATE_DIR}/current_story-${SESSION_ID}.json"

# Extract the raw prompt text and look for a .squad/plans/**/*.md reference.
PLAN_PATH="$(python3 - "$INPUT_JSON" <<'PY'
import json, re, sys

try:
    data = json.loads(sys.argv[1] or "{}")
except Exception:
    data = {}

prompt = data.get("prompt", "") or ""

# Matches things like:
#   .squad/plans/foundation/04-story-451.md
#   squad/plans/foundation/04-story-451.md  (no leading dot)
m = re.search(r"(\.squad/plans/[^\s\"']+\.md)", prompt)
if m:
    print(m.group(1))
PY
)"

if [[ -z "${PLAN_PATH}" ]]; then
    # Nothing plan-related in this prompt — leave any existing state alone.
    exit 0
fi

ABS_PLAN_PATH="${REPO_ROOT}/${PLAN_PATH}"
FILENAME="$(basename "${PLAN_PATH}")"

# --- Derive plan_number / story_id -----------------------------------------
read -r PLAN_NUMBER STORY_ID <<PYOUT
$(python3 - "${FILENAME}" "${ABS_PLAN_PATH}" <<'PY'
import re, sys

filename, abs_path = sys.argv[1], sys.argv[2]

plan_number, story_id = "00", "000"

# 1. Filename pattern: NN-story-ID.md
m = re.match(r"^(\d+)-story-(\d+)\.md$", filename)
if m:
    plan_number, story_id = m.group(1), m.group(2)
else:
    # 2. Fall back to content markers, if the file exists and is readable.
    try:
        with open(abs_path, "r", encoding="utf-8") as f:
            text = f.read()
        m_story = re.search(r"^#\s*Story\s+(\d+)", text, re.MULTILINE)
        if m_story:
            plan_number = m_story.group(1)
        m_id = re.search(r"\(Story:\s*(\d+)\)", text)
        if m_id:
            story_id = m_id.group(1)
        # last resort: leading digits in filename, e.g. "04-something.md"
        if plan_number == "00":
            m_lead = re.match(r"^(\d+)", filename)
            if m_lead:
                plan_number = m_lead.group(1)
    except FileNotFoundError:
        pass

print(plan_number, story_id)
PY
)
PYOUT

# --- Write the state file (scoped to this session) --------------------------
python3 - "${STATE_FILE}" "${PLAN_PATH}" "${PLAN_NUMBER}" "${STORY_ID}" <<'PY'
import json, sys
path, plan_path, plan_number, story_id = sys.argv[1:5]
with open(path, "w", encoding="utf-8") as f:
    json.dump(
        {"plan_path": plan_path, "plan_number": plan_number, "story_id": story_id},
        f,
        indent=2,
    )
PY

log "TRACKED session=${SESSION_ID} plan_path=${PLAN_PATH} plan_number=${PLAN_NUMBER} story_id=${STORY_ID}"
exit 0
