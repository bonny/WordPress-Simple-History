---
allowed-tools: Bash(gh issue create:*), Bash(gh project item-list:*), Bash(gh project item-edit:*), Bash(gh project item-add:*)
argument-hint: [issue-title]
description: Create a new GitHub issue and place it in the "In Progress" project column.
---

## Context

-   GitHub project configuration: See @CLAUDE.local.md for project IDs and status options
-   Issue title: $ARGUMENTS

## Your task

Create a new GitHub issue with the provided title and automatically add it to the "In Progress" column of the project board.

### Step 1: Create the GitHub issue

Create a new issue using the gh CLI:

```bash
gh issue create --title "$ARGUMENTS" --body ""
```

This will return the issue number (e.g., #123). Capture this number for the next steps.

### Step 2: Wait for project sync

GitHub needs a moment to sync the issue to the project board. Wait 2-3 seconds.

### Step 3: Get the project item ID

Use the project item list command to find the newly created issue's item ID:

```bash
gh project item-list 4 --owner bonny --format json | jq '.items[] | select(.content.number == ISSUE_NUMBER) | .id'
```

Replace `ISSUE_NUMBER` with the issue number from Step 1.

### Step 4: Move to "In Progress" status

Using the GitHub project configuration from @CLAUDE.local.md, move the issue to "In Progress":

```bash
gh project item-edit --id <item-id> --field-id PVTSSF_lAHOAANhgs4AidMqzga-LME --project-id PVT_kwHOAANhgs4AidMq --single-select-option-id 36813ba3
```

Replace `<item-id>` with the ID from Step 3.

### Step 5: Confirm to user

Report back to the user:
- The issue number that was created
- Confirmation that it was moved to "In Progress"
- A link to the issue (gh will show this when creating the issue)
