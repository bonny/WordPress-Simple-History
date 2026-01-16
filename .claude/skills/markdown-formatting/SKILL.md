---
name: markdown-formatting
description: Formats markdown files using Prettier for consistent style. Runs after creating or updating markdown (.md) files. Triggers: "format markdown", "prettier", "fix md formatting".
allowed-tools: Bash
---

# Markdown Formatting

Format markdown files using Prettier for consistent style.

## Commands

### Format a Single File

```bash
npx prettier --write path/to/file.md
```

### Format All Markdown Files

```bash
npx prettier --write "**/*.md"
```

### Check Without Writing

```bash
npx prettier --check path/to/file.md
```

## Prettier Markdown Defaults

Prettier applies these rules to markdown:

- **Prose wrap**: `preserve` (respects original line breaks)
- **Tab width**: 2 spaces
- **Trailing newline**: Yes
- **Consistent list markers**: `-` for unordered lists
- **Table formatting**: Aligned columns

## Example Usage

After editing `readme.issue-608-alerts.md`:

```bash
npx prettier --write readme.issue-608-alerts.md
```

## Notes

- Prettier is available via npx (version 3.0.3)
- No configuration file needed - uses sensible defaults
- Safe to run multiple times (idempotent)
