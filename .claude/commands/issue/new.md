---
allowed-tools: Bash(gh issue create:*), Bash(gh project item-list:*), Bash(gh project item-edit:*), Bash(gh project item-add:*)
argument-hint: [issue-title]
description: Create a new GitHub issue and place it in the "In Progress" project column.
---

## Context

-   GitHub project configuration: See @AGENTS.md for project IDs and status options
-   Issue title: $ARGUMENTS

## Your task

Create a new GitHub issue with the provided title and automatically add it to the "In Progress" column of the project board.

### Step 1: Ask for issue description

Use the AskUserQuestion tool to ask the user for a brief description of the issue:

- Question: "Please provide a brief description for this issue (optional - leave blank for no description)"
- Header: "Description"
- Options:
  - "Skip" - "Create issue without a description"
  - "Provide description" - "I'll provide a description in the next step"

If the user chooses "Provide description", ask a follow-up question with a text input for the description.

### Step 2: Create the GitHub issue

Create a new issue using the gh CLI:

**If user provided a description:**
```bash
gh issue create --title "$ARGUMENTS" --body "DESCRIPTION_FROM_USER"
```

**If user skipped description:**
```bash
gh issue create --title "$ARGUMENTS" --body ""
```

This will return the issue URL (e.g., https://github.com/bonny/WordPress-Simple-History/issues/589).
Extract the issue number from the URL for the next steps.

### Step 3: Add issue to project

GitHub doesn't automatically add new issues to the project board, so we need to add it manually:

```bash
gh project item-add 4 --owner bonny --url <ISSUE_URL>
```

Replace `<ISSUE_URL>` with the URL from Step 2.

### Step 4: Wait for project sync

GitHub needs a moment to sync the newly added item. Wait 2-3 seconds.

### Step 5: Get the project item ID

Use the project item list command to find the newly created issue's item ID:

```bash
gh project item-list 4 --owner bonny --format json | jq '.items[] | select(.content.number == ISSUE_NUMBER) | .id' -r
```

Replace `ISSUE_NUMBER` with the issue number from Step 2.

If this returns empty, wait another 2-3 seconds and try again.

### Step 6: Move to "In Progress" status

Using the GitHub project configuration from @AGENTS.md, move the issue to "In Progress":

```bash
gh project item-edit --id <item-id> --field-id PVTSSF_lAHOAANhgs4AidMqzga-LME --project-id PVT_kwHOAANhgs4AidMq --single-select-option-id 36813ba3
```

Replace `<item-id>` with the ID from Step 5.

### Step 7: Confirm to user

Report back to the user:
- The issue number that was created
- Whether a description was added
- Confirmation that it was added to the project and moved to "In Progress"
- A link to the issue (from Step 2)
