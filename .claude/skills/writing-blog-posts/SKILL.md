---
name: writing-blog-posts
description: Writes blog posts for simple-history.com matching the author's established voice and style. Drafts content, creates announcements, writes marketing copy, and reviews blog tone. Triggers: "write blog post", "draft post", "blog content", "announcement".
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

**Voice:** Conversational, knowledgeable, friendly expert
**Structure:** Short paragraphs (2-4 sentences), clear headings, bullets for lists
**Pronouns:** Address readers as "you"
**Avoid:** "dive into", "let's explore", "in today's digital landscape", excessive exclamation marks

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

- **Creating new posts:** Always include `"author": 1` in the request.
- **Updating existing posts:** Check the current author first. If the author is Claude, set it to `"author": 1`. If the author is anyone other than Claude or Pär, leave it unchanged.

## Context

- **Product:** Simple History - WordPress activity log plugin
- **Audience:** WordPress site owners, developers, agencies
- **Upselling:** Follow wordpress-org-compliance skill guidelines

## Resources

- [voice-samples.md](voice-samples.md) - Excerpts defining target voice
- [style-guide.md](style-guide.md) - Detailed writing rules
- [reference-blogs.md](reference-blogs.md) - Source blogs for additional inspiration
