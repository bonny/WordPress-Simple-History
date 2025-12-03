---
name: git-commits
description: Create well-structured git commits in logical chunks following best practices. Use when the user asks to commit, says "commit", or after completing code changes that should be committed.
---

# Git Commits in Logical Chunks

Organize commits into logical, atomic chunks - each representing a single coherent change.

## When to Split vs Combine

**Separate into different commits:**
- CSS changes vs PHP logic changes
- Different features (even if in same file)
- Refactoring vs new functionality
- Multiple bug fixes (one per fix)

**Keep together in one commit:**
- Related changes across files for one feature
- A handler + its CSS for the same feature
- Tests for the feature being added

## Commit Order

When you have multiple changes:
1. Infrastructure/config (build tools, dependencies)
2. Core functionality (PHP handlers, services)
3. UI/styling (CSS, templates)
4. Documentation

## Checklist

Before committing, ask:
1. Does this commit do ONE thing?
2. Could I write a clear, short summary?
3. If I revert this, would it make sense on its own?

## Examples

### Good: Logical Separation

```
Commit 1: "Add CSS classes for checkbox grid layout"
Commit 2: "Update import handler for flexible date range"
Commit 3: "Document backfill form UX improvements"
```

### Bad: Mixed Concerns

```
"Various updates to backfill feature"
```

## Multiple Repositories

When changes span core + premium repos:
1. Commit core changes first, then premium
2. Use related commit messages
