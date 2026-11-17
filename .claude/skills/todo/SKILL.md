---
name: todo
description: "This project's todos: one file per todo in `todos/` with YAML frontmatter; `todo.md` is generated. Use when asked about todos here — 'todos', 'what's next', 'show #N', 'new todo …', '#N is done', 'reject #N', 'the follow-up for #N is done', 'postpone #N'."
---

# /todo

The convention, file format, size rules and every command are in the shared skill
**`../.claude/skills/todos/SKILL.md`** (in `~/Projects`). Read it before changing a todo.

Short version:

- A todo is `todos/NN-slug.md`. `status`, `state`, `human`, `blocked_by` live in the
  frontmatter, follow-ups as lines under `## Follow-ups`. `todo.md` is generated; never edit it.
- Change todos with the script: `python3 ../.claude/skills/todos/todos.py <command> --project sh`
  (`new`, `set`, `done`, `reject`, `followup`, `tick`, `move`). After a hand edit run
  `index --project sh`, and `check` before you're done.
- **What's next:** `agent --project sh` and `status active`; suggest one and say why.
- **Show N:** `show SH-N` and read the file.
- **Done:** `done sh N "result"` with a 2–5 line result; trim the log to the decisions.
- Never run prettier on the todo files. Commit only when Pär asks.

## Project-specific

- Prefix `SH-01`. Todos are written in English. Most are drafts Pär posts himself (support
  replies, feature pages, blog material): create them with `new sh "Title" --status active
  --human "post the reply"` and put the draft in the body. Never publish.
- Branch work keeps using `readme.<branch-or-issue>.md` (see `AGENTS.md`); a todo is for what
  outlives a branch.
