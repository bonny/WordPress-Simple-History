---
name: writing-blog-posts
description: Writes blog posts for simple-history.com matching the author's voice and style. Use when drafting posts, announcements, or marketing copy.
allowed-tools: Read, WebFetch
---

# Writing Blog Posts

Create blog posts for simple-history.com that match the author's established voice.

## Process

1. Read [voice-samples.md](voice-samples.md) to understand target tone
2. Apply [style-guide.md](style-guide.md) guidelines
3. Draft content matching the voice samples
4. Iterate based on feedback

## Quick Reference

**Voice:** Conversational, knowledgeable, friendly expert — must sound human, not AI-generated
**Structure:** Short paragraphs (2-4 sentences), clear headings, bullets for lists
**Pronouns:** Address readers as "you"
**Avoid:** "dive into", "let's explore", "in today's digital landscape", excessive exclamation marks

### Sound Human, Not AI

Blog posts must read like a real person wrote them. Watch for these AI tells:

-   **Mechanical summary patterns** — "The article highlights X, mentions Y, and notes Z" chains
-   **Overly polished/balanced structure** — every paragraph perfectly formed, no rough edges
-   **Flattering self-references** — "the description is flattering", "we're pleased to share"
-   **Corporate filler** — "covers the core use case", "key takeaways", "it's worth noting"
-   **No personality or opinion** — real posts push back, joke, or admit things honestly
-   **Formulaic closings** — "Thanks for the mention, [Name]"

Instead: have an opinion, acknowledge criticism honestly, use casual phrasing, and let some sentences be short or incomplete. Read it back and ask "would a person actually write this?"

## Post Template

```markdown
# [Title - Promise a Benefit]

[Hook: 1-2 sentences on problem/opportunity]

## [Section Headings]

[Content with examples]

## Conclusion

[Summary + call-to-action]
```

## Author

**Never set Claude as the post author.** The author must be Pär (user ID 1).

-   **Creating new posts:** Always include `"author": 1` in the request.
-   **Updating existing posts:** Check the current author first. If the author is Claude, set it to `"author": 1`. If the author is anyone other than Claude or Pär, leave it unchanged.

## Context

-   **Product:** Simple History - WordPress activity log plugin
-   **Audience:** WordPress site owners, developers, agencies
-   **Upselling:** Follow wordpress-org-compliance skill guidelines

## Resources

-   [voice-samples.md](voice-samples.md) - Excerpts defining target voice
-   [style-guide.md](style-guide.md) - Detailed writing rules
-   [reference-blogs.md](reference-blogs.md) - Source blogs for additional inspiration
