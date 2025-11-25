---
name: changelog
description: Add an entry to the changelog in readme.txt following the project's changelog format
---

# Add Changelog Entry

You are tasked with adding a changelog entry to the Simple History plugin's readme.txt file.

## Instructions

1. Ask the user for the changelog entry text (one line summary of the change)
2. Read the `readme.txt` file to find the `## Changelog` section
3. Locate the `### Unreleased` subsection
4. Add the new entry as the **first item** under `### Unreleased` (most recent entries go on top)
5. Follow the exact format:
   - Start with hyphen and 3 spaces: `-   `
   - Use past tense for completed work: "Fixed...", "Added...", "Changed...", "Removed..."
   - Be concise and user-focused (explain the user-facing issue/feature, not technical implementation)
   - End with a period
6. Show the user the added entry and confirm it looks correct

## Format Examples

Good examples from existing changelog:
- `-   Fixed post creation via Gutenberg autosave not being logged, causing email reports to show 0 posts created.`
- `-   Add developer mode badge to the page header.`
- `-   Fixed timezone and date handling issues in email reports.`
- `-   Add WordPress VIP Go coding standards for enterprise compatibility.`
- `-   Fixed post creation via Gutenberg autosave not being logged. [#599](https://github.com/bonny/WordPress-Simple-History/issues/599)`

## Guidelines

- **Be specific**: Mention the feature/component affected (e.g., "email reports", "Gutenberg autosave")
- **User-focused**: Explain what users will notice, not how it was implemented
- **Concise**: Keep to one line when possible
- **Active voice**: "Fixed X" not "X was fixed"
- Link to GitHub issue or pull request if available

## WordPress Changelog Best Practices

Based on [WordPress Developer Blog guidance](https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/):

### Writing Style
- **Write for end-users, not developers** - Avoid technical jargon
- **Be specific, not vague** - "Fixed a PHP 8.2 deprecation warning causing admin dashboard errors" NOT "Bug fixes"
- **Explain the impact** - Include context about why changes matter (performance, security, workflow improvements)
- **Use accessible language** - Avoid cultural references or overly technical terminology

### Structure
- Use clear category labels: Added, Changed, Fixed, Deprecated, Removed, Security
- Maintain consistency across releases
- Each entry should be actionable and descriptive

### Good vs Bad Examples

❌ **Problematic:**
- "Bug fixes"
- "Various improvements"
- "Updated code"

✅ **Effective:**
- "Fixed post creation via Gutenberg autosave not being logged, causing email reports to show 0 posts created"
- "Added developer mode badge to improve debugging workflow"
- "Fixed timezone handling issues that caused email reports to show incorrect dates"

## File Location

- File: `readme.txt` (in project root)
- Section: `## Changelog` → `### Unreleased`

## Notes

- If "Unreleased" section doesn't exist, create it right after "## Changelog"
- Entries use format from https://keepachangelog.com and WordPress best practices
- Most recent entries appear first in the list
