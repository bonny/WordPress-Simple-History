---
name: github-project
description: Use when working with the Simple History GitHub project board — updating issue status, querying board items, or automating project workflows.
allowed-tools: Bash
---

# GitHub Project Board

## Project Configuration

-   **Project**: Simple History Kanban
-   **URL**: https://github.com/users/bonny/projects/4/views/1
-   **Project ID**: `PVT_kwHOAANhgs4AidMq`
-   **Project number**: `4`
-   **Owner**: `bonny`
-   **Status field ID**: `PVTSSF_lAHOAANhgs4AidMqzga-LME`

## Board Columns & Status Option IDs

| Column       | Option ID  |
| ------------ | ---------- |
| Backlog      | `25e9263f` |
| To do        | `6c3f4438` |
| In progress  | `36813ba3` |
| Experimental | `52a48e60` |
| Done         | `c40edce0` |

## GitHub CLI Commands

```bash
# List open issues
gh issue list --state open

# View specific issue
gh issue view NUMBER

# Access project board (requires read:project scope)
gh api graphql -f query='
  query {
    user(login: "bonny") {
      projectV2(number: 4) {
        title
        items(first: 50) {
          nodes {
            content {
              ... on Issue {
                title
                number
                state
              }
            }
          }
        }
      }
    }
  }
'
```
