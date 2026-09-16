---
name: simple-history-voice
description: Pär's writing voice and per-text-type rules for Simple History, on top of the humanizer skill. Use whenever writing or editing text people will read — changelog entries, GUI copy and notices, blog posts, newsletters, customer emails, docs, commit messages and PR descriptions.
---

# Simple History Voice

Text for Simple History should read like Pär wrote it, not an AI. This skill covers what is specific to this project. The general AI tells (not-X-but-Y, one-line closers, triads, dashes, inflated claims, sales words) live in the **humanizer** skill, installed at user level.

## Workflow

1. Write the draft following the voice and text-type rules below.
2. Run it through **humanizer** in embedded mode (return only the final text). For blog posts and newsletters, give it a sample of Pär's writing to match, such as the [5.32.0 release post](https://simple-history.com/2026/simple-history-5-32-0-released/).
3. Check the result against the banned words in `code.md` → "Writing Prose".

If humanizer isn't installed, tell the user (`npx skills add blader/humanizer --global`) and apply the rules below on your own.

## Voice

-   One developer writing to one reader. Use "I", not "we" or "the team".
-   Plain and friendly, a regular nice developer. Not a marketer, not corporate.
-   Specific over general: the version number, the real button label, the real screen name ("Simple History → Settings").
-   Small honest opinions are welcome ("Technically correct, and no use at all", "I should have caught that earlier"). **Never invent** feelings, anecdotes or history for Pär. Suggest a line marked "(only if true)" instead.
-   Phrases Pär uses himself are his voice, even if they look like AI tells. "genuinely helps" is fine.

## By text type

**Changelog entries** (see the `changelog` skill). One line each, what changed for the user, no selling. "Weekly email includes a plain-text version." No em dashes.

**GUI copy** (notices, labels, buttons, empty states, errors). Short and concrete. Say what happened and what to do. Button labels are verbs ("Turn on weekly email"). No exclamation marks, no "Oops!", no em dashes.

**Release blog posts** (see the `release` skill, and `writing-blog-posts` if available). Title: "Simple History X.Y.Z released: <main feature>", not a slogan. Open with what changed and why it matters to the reader. Screenshot captions stand on their own: name the feature, link its feature page, mark Premium-only content.

**Newsletters and customer emails.** Written to one person. No corporate warm-up or sign-off clichés.

**Upsell copy** (see `premium-upsell-design`). Name the concrete thing Premium shows or does: "Premium shows which posts were edited and by whom." Never "unlock powerful insights". Don't oversell, and no buzzwords like "forensics".

**Commit messages and PR descriptions.** Say what changed and why. Don't describe how careful the writing was ("preserved", "ensured", "carefully").

## Don't overcorrect

Correct and clear comes first. Keep facts, links and numbers exactly as they are.
