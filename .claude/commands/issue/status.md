---
allowed-tools: Bash(git add:*), Bash(git status:*), Bash(git commit:*)
description: Find the status of the issue.
---

## Context

-   Current git status: !`git status`
-   Current git diff (staged and unstaged changes): !`git diff HEAD`
-   Current branch: !`git branch --show-current`
-   Issue readme files: !`ls readme.issue-*.md 2>/dev/null || echo "No issue readme files found"`
-   Issue number: $ARGUMENTS

## Your task

Tell the user the status of the issue by following these steps in order:

1. **Determine the issue number**:
   - If an issue number is passed as `$ARGUMENTS`, use that
   - Otherwise, extract the issue number from the current branch name (e.g., `issue-584-...` → issue #584)

2. **Read the issue readme file**:
   - Look for `readme.issue-<number>-*.md` file in the project root
   - If found, read and display the key sections:
     * Status line (look for "Status:" or "**Status**:")
     * Summary or overview
     * Implementation status/progress
     * Any pending tasks or follow-up issues
   - If multiple readme files exist for the issue, read the main one (without "subissue" in the name)
   - If no readme file exists, note that and continue

3. **Check GitHub status**:
   - Use `gh issue view <issue-number>` to get the GitHub issue status
   - Show: title, state (open/closed), labels, assignees

4. **Check project board status** (if applicable):
   - Use GitHub CLI to check which column the issue is in on the project board
   - Project: "Simple History kanban" (Project #4)

5. **Summarize git status**:
   - Show current branch
   - Show if there are uncommitted changes
   - Show if branch is ahead/behind remote

Present the information in a clear, organized format highlighting the most important status information at the top.
