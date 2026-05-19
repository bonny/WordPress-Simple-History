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
-   **Marketing-brochure connectors** — "pairs naturally with", "works seamlessly", "complements"
-   **Filler phrases that sound thorough** — "across the board", "out of the box", "from the ground up"
-   **Negative framing in headings** — "X is no longer Y" reads as AI. Flip to the positive outcome ("X now Y").
-   **No personality or opinion** — real posts push back, joke, or admit things honestly
-   **Formulaic closings** — "Thanks for the mention, [Name]"

Instead: have an opinion, acknowledge criticism honestly, use casual phrasing, and let some sentences be short or incomplete. Read it back and ask "would a person actually write this?"

## Release Posts ("What's New" Posts)

Release posts have a narrower audience than general blog posts: **existing users of the plugin**, not new visitors. They already know what Simple History does. They want to know what's new for them, quickly.

### Length

For an incremental release, the narrative section above the Full changelog block should be ~3,000–4,000 characters (roughly 90 seconds of reading). A marquee feature release (like 5.27.0 — AI agent attribution) can run longer; an incremental release (like 5.28.0) should be tighter. **If the body just restates what the Full changelog lists, cut it.** The changelog is the complete reference; the narrative is the "what's important and why you care" version.

### Sections

3–4 H2 sections is plenty for an incremental release. One per headline feature, optionally one "Other highlights" bullet list pointing into the changelog, then "Upgrading". Don't write a section per feature when several can share a heading.

### Lead callout

The `is-style-info` opening paragraph is for the **headline benefit**, not a full feature spec list. A returning user just needs the punchline. If the body section right below it enumerates the same things, the lead enumeration is redundant — cut the duplicate from the lead.

Also: don't re-explain the product. "Simple History is a plugin that…" never belongs in a release post lead. Start at the news.

### Audience fit — de-jargon for non-devs

The audience is mostly site owners, agencies, and freelancers. A minority are developers. For each technical reference, ask: _does a non-developer user need this phrasing to understand the value?_

-   ❌ "Post update events expose status, publish date, and author as structured data in the REST API."
-   ✅ "Post update events now include more detail when you use 'Copy as JSON' or 'Copy as Markdown' — status, author, and publish date are all there."

Keep the technical version in the Full changelog block where developers go for the complete reference. The narrative is the user-friendly version.

### Screenshots

For an incremental release, ~3 screenshots is right. More creates visual noise relative to the prose. Aim for: one for each headline feature (showing what it looks like in the activity log), plus one for any meaningful UI change. Skip screenshots of micro-interactions (button states, tooltips) — they don't carry as stills.

### CTA / Upgrading section

Keep it brief: one paragraph. Installation instructions (it's an auto-update via the WordPress dashboard), an "open an issue if something looks off" link, and a "five-star review genuinely helps" ask. No upgrade-to-premium push in release posts — release posts are for current users on the version that just shipped.

### Tone for release posts specifically

Acknowledging honesty earns trust. Lines like _"This one has bothered us for a while"_ or _"We had a handful of triage reports that tracked back to exactly this"_ are the most human moments — keep them. Marketing-brochure language ("game-changing", "seamlessly integrated", "across the board") is the opposite — cut it.

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
