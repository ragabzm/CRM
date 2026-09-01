Audit the plan file at `.squad/plans/foundation/$ARGUMENTS` against what is **actually** in the codebase right now.

Do not trust any prior chat summary, status report, or your own memory of having built this — those may be wrong or incomplete, which is exactly why this audit exists. Every claim below must be backed by something you just checked (reading the actual file, grepping for the actual class/route/column, or running the actual test).

For every line under `## Done Criteria` in that file:

1. Independently verify it against the real code: read the relevant files, grep for the relevant classes/routes/migrations, and run the relevant tests. Do not mark something true because it "should" exist per the plan — confirm it exists and works.
2. If it is genuinely true, mark that line `- [x]`.
3. If it is not true yet (missing, stubbed, partially wired, or the test doesn't actually pass), **implement or fix it now**, then mark it `- [x]` once you've verified it for real. This item is already tracked in this plan file, so finishing it is not a reportable event and does not belong in any gaps file — just do it and check it off.
4. If while doing this you discover something broken, missing, or inconsistent that is **not described anywhere** in this plan file, do not fold it into your summary or leave it unmentioned. Append it to `.squad/gaps/<plan_number>-<story_id>.md` (create the `.squad/gaps/` directory and that file if they don't exist — name it after this plan's own number/id, e.g. `04-451.md`) with a short heading and description, then keep going.

Do not stop until every checkbox under `## Done Criteria` in this file accurately reflects reality — no optimistic checkmarks, no boxes left unchecked because you ran out of budget without saying so.

When finished, give one short paragraph: what was already true, what you had to actually implement, and what (if anything) got logged to the gaps file.
