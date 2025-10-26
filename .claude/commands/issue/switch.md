---
allowed-tools: Bash(git add:*), Bash(git status:*), Bash(git commit:*)
argument-hint: [issue-number]
description: Switch to issue and make sure to be on the correct branch.
---

## Context

-   Current git status: !`git status`
-   Current git diff (staged and unstaged changes): !`git diff HEAD`
-   Current branch: !`git branch --show-current`

## Your task

We want to start working on issue #$1.

### Step 1: Check for uncommitted changes

First make sure there are no uncommitted changes in the current branch. If there are, then stop and ask the user to commit or stash the changes.

### Step 2: Get issue details

Use `gh issue view $1` to get the issue details. This will show:
- Issue title (useful for branch naming)
- Current project board status (e.g., "projects: Simple History kanban (In progress)")
- Issue description and metadata

### Step 3: Check if branch exists and switch/create

Check if the branch for this issue already exists using `git branch -a | grep -E "issue-$1|$1"`.

**If branch exists:**
- Switch to it with `git checkout <branch-name>`

**If branch doesn't exist:**
- Create it based on the main branch using the naming convention: `issue-$1-brief-description`
- Switch to the new branch
- Create an issue readme file named `readme.issue-$1-brief-description.md` and write a short description of the issue based on the gh issue view output

### Step 4: Check for and read issue readme

Use Glob to check if an issue readme file exists with pattern `readme.issue-$1-*.md`.

**If it exists:**
- Read it to understand the current status, progress, and context
- Provide a brief summary to the user of what's in the readme

**If it doesn't exist:**
- Note that no readme exists (this is expected for newly created branches)

### Step 5: Verify and update project board status

Based on the output from `gh issue view $1` in Step 2, check if the issue shows "In progress" in the projects field.

**If issue is already "In progress":**
- No action needed, just confirm to the user

**If issue is NOT "In progress":**
- Get the item ID from the project using: `gh project item-list 4 --owner bonny --format json`
- Move it to "In progress" using:
  ```bash
  gh project item-edit --id <item-id> --field-id PVTSSF_lAHOAANhgs4AidMqzga-LME --project-id PVT_kwHOAANhgs4AidMq --single-select-option-id 36813ba3
  ```

### Project Details Reference

See @CLAUDE.local.md for GitHub project configuration including:
- Project ID, number, and owner
- Status field ID
- Status option IDs (including "In progress")
