---
name: local-issues
description: Use when working with local Obsidian issues — querying, creating, updating status/properties, adding agent notes, or searching issue content. Triggered when user mentions local issues, Obsidian issues, or when you need to look up issue status, priorities, or details.
allowed-tools: Bash, Read, Edit
---

# Local Issues via Obsidian CLI

Manage local issue files in Obsidian using the `obsidian` CLI. This is faster and more reliable than manually reading/editing files for structured issue operations.

## Setup

All commands require:

-   `vault=nvALT` — the vault containing Simple History issues

**JSON output:** Most commands support `format=json` for structured output. The `jq` CLI tool is installed locally and can be used to filter/transform JSON results.

Issue files live at: `/Users/bonny/Documents/nvALT/Simple History/issues/`
Archived issues: `/Users/bonny/Documents/nvALT/Simple History/issues/archive/`

## Querying Issues

The issues base is `Simple History issues.base`. Always use `path=` (not `file=`) because a `.md` file with the same name exists and `file=` resolves like wikilinks, picking the `.md` first.

```bash
# List available views
obsidian base:views vault=nvALT path="Simple History issues.base"
# Views: Alla, Todo, Needs investigation, Needs decision, Idea, In progress, Done

# Query a filtered view (JSON array — includes path, name, and all frontmatter properties)
obsidian base:query vault=nvALT path="Simple History issues.base" view="Todo" format=json

# Compact table output
obsidian base:query vault=nvALT path="Simple History issues.base" view="Todo" format=tsv

# Query all issues (no view filter)
obsidian base:query vault=nvALT path="Simple History issues.base" format=json

# Use jq (installed locally) to filter/transform JSON output
obsidian base:query vault=nvALT path="Simple History issues.base" view="Todo" format=json | jq '[.[] | {name, status, prio}]'
```

**Prefer `base:query` over grep** for listing/filtering issues — one call returns structured data for all matching issues.

## Reading and Modifying Properties

```bash
# Read a specific property value
obsidian property:read vault=nvALT name=status path="Simple History/issues/Some Issue.md"

# Read all properties as JSON
obsidian properties vault=nvALT path="Simple History/issues/Some Issue.md" format=json

# Set a property (updates frontmatter in-place, creates if missing)
obsidian property:set vault=nvALT name=status value=in-progress path="Simple History/issues/Some Issue.md"

# Remove a property
obsidian property:remove vault=nvALT name=review path="Simple History/issues/Some Issue.md"
```

### Frontmatter Schema

```yaml
type: bug # bug, feature, ux, idea, website, perf, docs
prio: 1-high # 1-high, 2-normal, 3-low
size: 1-small # 1-small, 2-medium, 3-large
complexity: patch # patch (single commit), branch (needs own branch)
status: todo # todo, in-progress, done, needs-investigation, needs-decision, idea, blocked
review: pending # pending, done — set to pending when agent marks status as done
```

All fields are optional. Number-prefixed values sort correctly in Obsidian.

## Reading and Writing Content

```bash
# Read full file (frontmatter + body)
obsidian read vault=nvALT path="Simple History/issues/Some Issue.md"

# Append content (e.g., agent notes)
obsidian append vault=nvALT path="Simple History/issues/Some Issue.md" content="\n> [!agent]\n> **Finding:** Details here..."

# Prepend content
obsidian prepend vault=nvALT path="Simple History/issues/Some Issue.md" content="Updated priority based on user feedback.\n"
```

## Creating Issues

```bash
# Create via the base (uses base's configured folder)
obsidian base:create vault=nvALT path="Simple History issues.base" name="New Issue Title"

# Create directly, then set properties
obsidian create vault=nvALT path="Simple History/issues/New Issue Title.md" content="Description here"
obsidian property:set vault=nvALT name=type value=bug path="Simple History/issues/New Issue Title.md"
obsidian property:set vault=nvALT name=status value=todo path="Simple History/issues/New Issue Title.md"
```

## Searching Issues

```bash
# Search issue files for text (returns file paths)
obsidian search vault=nvALT query="REST API" path="Simple History/issues" format=json

# Search with context (shows matching lines)
obsidian search:context vault=nvALT query="REST API" path="Simple History/issues" format=json
```

For regex or line-number-precise searches within issue bodies, fall back to Grep on `/Users/bonny/Documents/nvALT/Simple History/issues/`.

## Typical Workflows

**Pick up next issue to work on:**

```bash
obsidian base:query vault=nvALT path="Simple History issues.base" view="Todo" format=tsv
```

**Start working on an issue:**

```bash
obsidian property:set vault=nvALT name=status value=in-progress path="Simple History/issues/Issue Name.md"
```

**Mark issue done with agent notes:**

```bash
obsidian append vault=nvALT path="Simple History/issues/Issue Name.md" content="\n> [!agent]\n> **Completed:** Summary of what was done..."
obsidian property:set vault=nvALT name=status value=done path="Simple History/issues/Issue Name.md"
obsidian property:set vault=nvALT name=review value=pending path="Simple History/issues/Issue Name.md"
```

**Present a `review: pending` issue for human review:**

When the user asks to look at an issue for review, always provide:

1. **Summary** — what was the original request and what was done
2. **Files changed** — list each file with the relevant line numbers and a short description of the change
3. **What to verify** — concrete, actionable steps for a human reviewer to confirm the change works correctly

Then ask the user to confirm or mark it done. Once review is approved, archive the issue (see "Archiving Done Issues" below).

## Archiving Done Issues

When an issue has `status: done` and `review: done`, move it to the archive folder:

```bash
mv "/Users/bonny/Documents/nvALT/Simple History/issues/Issue Name.md" \
   "/Users/bonny/Documents/nvALT/Simple History/issues/archive/"
```

Archived issues stay searchable in Obsidian but don't appear in active base views. To find archived issues:

```bash
# Search archived issues
obsidian search vault=nvALT query="search term" path="Simple History/issues/archive" format=json

# Or use Grep directly
# Grep pattern="search term" path="/Users/bonny/Documents/nvALT/Simple History/issues/archive"
```

## Inbox

The quick-capture inbox is a single file, not part of the base:

-   Path: `/Users/bonny/Documents/nvALT/Simple History/Inbox for Simple History.md`
-   Structure: `# Inbox` / `# In Progress` / `# Done` sections
-   Use Read/Edit tools directly for inbox operations (it's one file, no need for CLI)
