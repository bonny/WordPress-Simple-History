---
name: quick-wins
description: Surface and triage local issues that are safe and quick for an AI to implement and easy for a human to verify. Use when the user wants to "knock out issues", asks for "quick wins", "low-hanging fruit", "what's easy to do right now", or wants a batch of small tasks to work through in one session.
allowed-tools: Bash, Read
disable-model-invocation: true
---

# Quick Wins — find AI-friendly issues to knock out

Use this skill when the user wants to power through a batch of small issues. The goal is to identify issues where:

1. **The spec is already done** — root cause, file paths, or implementation hints are in the issue body
2. **The blast radius is small** — touches one logger / one command / one screen, not architecture
3. **A human can verify in <2 minutes** — clear "do X, see Y" check
4. **Failure mode is obvious** — if it breaks, you'll see it immediately (not a slow data corruption)

## Workflow

### Step 1 — list candidate issues

Pull all `2-todo` issues with their size/complexity:

```bash
obsidian base:query vault=nvALT path="Simple History issues.base" format=json \
  | jq '[.[] | select(.status == "2-todo") | {name, prio, type, size, complexity}]'
```

Filenames also act as a length proxy (longer file = more spec written down):

```bash
ls -S "/Users/bonny/Documents/nvALT/Simple History/issues/" | head -30
# Or sort by line count:
wc -l "/Users/bonny/Documents/nvALT/Simple History/issues/"*.md | sort -n
```

Very short issues (<25 lines) are usually stubs without enough context — skip them unless the title alone is enough.
Very long issues are often well-spec'd but may have grown in scope — read carefully.

### Step 2 — score each candidate

Read the body and rate against these signals. Don't invent scores; just check off what's true.

**Green flags (more = better)**

-   🟢 Issue body names a specific file + line for the fix (e.g. "`class-post-logger.php:654`, add ...")
-   🟢 Has a `> [!agent]` decision block — means the design has already been debated
-   🟢 Marked `size: 1-small` or `complexity: patch`
-   🟢 Type is `bug` with reproducible steps
-   🟢 Verification is "run X, see Y" — no test data, no special account, no third-party service
-   🟢 Single-file or single-method change

**Red flags (any one is usually disqualifying)**

-   🔴 Touches the database schema or runs `ALTER TABLE` on existing data
-   🔴 Modifies billing, license validation, or update server logic
-   🔴 Crosses core ↔ premium boundary (without clear interface)
-   🔴 Type is `feature` and the body is a stub (<25 lines, no implementation hints)
-   🔴 Verification needs WooCommerce + a real order + a real product (unless WC is set up locally)
-   🔴 Status is `4-needs-decision` or `5-needs-investigation` — by definition not ready
-   🔴 Networks/multisite changes without a network test environment
-   🔴 UI redesigns (subjective taste calls — save for human-led work)

### Step 3 — present a tiered list

Group findings into three tiers and present to the user. Don't pick — let the user choose what to work on.

```
🟢 Tier 1 — Spec is done, just implement
- <Issue name> — one-line summary of fix + verification step
...

🟡 Tier 2 — Clear scope, slightly larger
- <Issue name> — ...

🔴 Skip for now (with reason)
- <Issue name> — too risky / underspecified / needs decision
```

### Step 4 — once user picks one

Follow the normal `local-issues` workflow:

1. `obsidian property:set ... status=1-in-progress`
2. Implement the change
3. Verify by running the steps in the issue
4. Run linters and relevant tests
5. Commit (per `git-commits` skill)
6. `obsidian property:set ... status=8-done` and `review=pending`
7. Append a `> [!agent]` block summarising what changed and how the user can verify

If the user says "do them all" or "knock them out", work through one at a time, marking each done before starting the next. Do **not** batch into a single commit — one issue, one commit, so review can happen per-issue.

## Anti-patterns — DON'T

-   ❌ Don't promote an issue to Tier 1 just because it's small. "Small + underspecified" still means hidden decisions.
-   ❌ Don't treat `size: 1-small` as the only signal — read the body.
-   ❌ Don't pick issues with `status: 4-needs-decision` or `5-needs-investigation` — they're parked for a reason.
-   ❌ Don't pick UX/redesign work without an explicit `> [!agent]` decision in the body.
-   ❌ Don't assume an issue is fresh — check if there's already an `agent` note saying it's partly done.

## Notes on common issue types in this repo

-   **WP-CLI bugs** (e.g. things-not-logged-from-CLI) → usually a missing `WP_CLI` exception alongside the `REST_REQUEST`/`XMLRPC_REQUEST` checks. Easy to verify with `wp` commands.
-   **Logger improvements** (extra context fields like SKU/ID) → see the `create-logger` and `logger-messages` skills. Verify via the populate-log skill or by doing the action and reading the event details.
-   **License/constant features** (premium) → small, isolated changes in the licensing code. Verify by `define()`-ing the constant and reloading the settings screen.
-   **WooCommerce features** → only safe if WC is installed locally and you can hit the action. If not, mark blocked and move on.
