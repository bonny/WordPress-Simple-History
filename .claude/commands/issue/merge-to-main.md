---
allowed-tools: Bash(git add:*), Bash(git status:*), Bash(git commit:*), Bash(git checkout:*), Bash(git merge:*), Bash(git branch:*), Bash(git push:*), Bash(rm:*), Bash(ls:*), Bash(gh project item-list:*), Bash(gh project item-edit:*), Bash(gh project field-list:*), Bash(gh api graphql:*)
description: Merge current issue branch into main branch.
---

## Context

-   Current git status: !`git status`
-   Current branch: !`git branch --show-current`
-   Issue readme files: !`ls readme.issue-*.md 2>/dev/null || echo "No issue readme files found"`

## Your task

Merge the current issue branch into the main branch after performing safety checks.

### Step 1: Verify current branch is an issue branch

Check if the current branch follows the naming convention `issue-NUMBER-description`.

If the branch doesn't match this pattern, stop and inform the user that this command only works with issue branches.

### Step 2: Check for uncommitted changes

Verify that there are no uncommitted changes using `git status`.

If there are uncommitted changes, stop and ask the user to commit or stash the changes first.

### Step 3: Extract issue number from branch name

Parse the issue number from the branch name (e.g., from `issue-588-black-friday-promotion`, extract `588`).

### Step 4: Remove issue readme file

Check if an issue readme file exists for this branch using the pattern `readme.issue-NUMBER-*.md`.

**If the readme file exists:**
- Delete it using `rm readme.issue-NUMBER-*.md`
- Commit the deletion with message: `Remove issue readme file`

**If no readme file exists:**
- Skip to next step

### Step 5: Merge to main

Execute the following steps:
1. Switch to main branch: `git checkout main`
2. Merge the issue branch: `git merge <issue-branch-name>`
3. Confirm successful merge to the user

### Step 6: Ask about project board status

Get the current project status by viewing the issue:
```bash
gh issue view NUMBER
```

Ask the user if they want to:
- Keep the issue in the "In progress" column
- Move the issue to the "Done" column

Use the AskUserQuestion tool with:
- Question: "Should this issue be moved to 'Done' on the project board?"
- Header: "Board status"
- Options:
  - "Keep in progress" - "Leave the issue in the 'In progress' column"
  - "Move to Done" - "Move the issue to the 'Done' column"

### Step 7: Update project board (if requested)

**If user chose "Move to Done":**

1. Get the "Done" status option ID by listing project fields:
   ```bash
   gh project field-list 4 --owner bonny --format json
   ```

2. Get the project item ID for this issue:
   ```bash
   gh project item-list 4 --owner bonny --format json | jq '.items[] | select(.content.number == NUMBER) | .id'
   ```

3. Move the issue to "Done" status using:
   ```bash
   gh project item-edit --id <item-id> --field-id PVTSSF_lAHOAANhgs4AidMqzga-LME --project-id PVT_kwHOAANhgs4AidMq --single-select-option-id <done-option-id>
   ```

4. Position the item at the top of the "Done" column using:
   ```bash
   gh api graphql -f query='
   mutation {
     updateProjectV2ItemPosition(
       input: {
         projectId: "PVT_kwHOAANhgs4AidMq"
         itemId: "<item-id>"
         afterId: null
       }
     ) {
       items(first: 1) {
         nodes {
           id
         }
       }
     }
   }'
   ```
   Note: `afterId: null` places the item at the top of the column.

**If user chose "Keep in progress":**
- No action needed, confirm to the user

### Step 8: Delete the feature branch

After successfully merging to main, clean up by deleting the feature branch:

1. **Store the branch name** before deletion (you're currently on main after the merge)
2. **Check if remote branch exists:**
   ```bash
   git ls-remote --heads origin <issue-branch-name>
   ```

3. **Delete the remote branch if it exists:**
   ```bash
   git push origin --delete <issue-branch-name>
   ```

4. **Delete the local branch:**
   ```bash
   git branch -d <issue-branch-name>
   ```

   Note: Use `-d` (not `-D`) to ensure the branch was properly merged. If `-d` fails, warn the user and don't force delete.

### Step 9: Summary

Provide the user with a summary:
- Branch that was merged
- Issue number
- Whether readme file was removed
- Whether local branch was deleted
- Whether remote branch was deleted (if it existed)
- Project board status (updated or kept as-is)
