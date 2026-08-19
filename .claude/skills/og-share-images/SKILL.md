---
name: og-share-images
description: Creates Open Graph / social share images (1200x630) for blog posts and announcements by rendering HTML in a headless browser. Use when a post needs an OG image, share card, or social preview graphic.
allowed-tools: Read, Write, Edit, Bash, Glob
---

# OG Share Images

Build share cards as HTML and render them headless. Not image editing — the
card is code, so it re-renders when the copy or numbers change.

The house style mocks up **real Simple History UI** (an event row, a settings
panel) rather than using stock art, and puts a joke or a concrete claim in the
headline. See [template.html](template.html) for a working example.

## Specs

Author at **1200x630**, render at **2x (2400x1260)**.

| Property   | Value                   | Why                                               |
| ---------- | ----------------------- | ------------------------------------------------- |
| Dimensions | 2400x1260               | 2x of the 1200x630 OG standard                    |
| Ratio      | 1.905:1                 | Facebook/LinkedIn canonical 1.91:1                |
| Colour     | 8-bit RGB, **no alpha** | transparency renders **black** on some platforms  |
| Format     | PNG                     | flat graphics + text; JPG only for photos         |
| Size       | under ~1 MB             | limits are 8 MB (FB) / 5 MB (X), so this is slack |

1200x630 satisfies Facebook, LinkedIn (1200x627), Slack, Discord and iMessage
without cropping. X is the one that crops — see below.

## Process

1. Copy [template.html](template.html) to the scratch dir and edit the copy,
   the mocked UI, and the design tokens.
2. Serve it. **`file:` URLs are blocked in the browser tool** — you must go
   over HTTP:
    ```bash
    cd <scratch-dir> && python3 -m http.server 8899 --bind 127.0.0.1
    ```
    Run it in the background; kill it when done.
3. Resize the viewport to **2400x1260**.
4. Navigate to `http://127.0.0.1:8899/og-image.html`. Append `?v=2`, `?v=3` …
   when re-rendering, or you will screenshot a cached page and think your edit
   did nothing.
5. Screenshot with **`scale: "css"`** (see the 2x gotcha below).
6. **Move the PNG out of the repo** (see gotcha below).
7. Run every check in [verify.py](verify.py).
8. Save the PNG **and its source HTML** somewhere durable. A scratch dir dies
   with the session, and then the card is no longer re-renderable.

## Gotchas

These each cost real time. Read them before starting.

### Screenshots land in the repo root

The browser tool writes to the current working directory, which is usually the
repo. On a release branch that means a stray PNG in your next commit. Move it
immediately and confirm:

```bash
mv <repo-root>/<name>.png <scratch-dir>/
git status --short   # must be clean
```

### Getting a true 2x

`scale: "device"` does **not** guarantee 2x — it follows the viewport's device
pixel ratio, which is 1 in headless, so you get 1200x630 back.

Instead put `html { zoom: 2; }` in the CSS, set the viewport to 2400x1260, and
screenshot with `scale: "css"`. `zoom` re-runs layout at the larger size, so
glyphs are _rendered_ at 2x rather than upscaled. Authoring stays in 1200x630
coordinates.

### 2x buys sharpness, not legibility

Doubling resolution does not make anything relatively bigger. If text is too
small on a phone, it is still too small at 2x. Legibility is fixed only by
changing sizes in the 1200-wide design space — or by cutting the element.

### X crops to ~2:1

X renders `summary_large_image` nearer 2:1, trimming **30px off the top and
bottom** at 2x. Keep those bands empty. `verify.py` checks this by counting
non-background pixels rather than eyeballing it.

### Emoji

Emoji render via the system font, so they only work on a machine that has one
(fine on macOS). They are also the most legible thing on a small card — colour
and shape survive downscaling better than text. Good place to put the joke.

## Legibility

Share cards render roughly **350-500px wide**. Check against the worst case,
400px — i.e. **one third** of the 1200-wide design space:

| Design size   | At 400px card | Verdict                          |
| ------------- | ------------- | -------------------------------- |
| 50px headline | 16.7px        | comfortable                      |
| 29px          | 9.7px         | fine for emoji and short numbers |
| 24px          | 8.0px         | floor for anything meaningful    |
| under 20px    | under 7px     | decorative only                  |

**Anything carrying meaning or humour needs ~24px minimum in design space.**
If the punchline does not clear that, make it bigger or cut it — do not ship a
joke nobody can read. `verify.py` prints this table.

## Design tokens

Matching WP admin / Simple History UI makes a mock look real:

| Token            | Value                                                               |
| ---------------- | ------------------------------------------------------------------- |
| Link blue        | `#2271b1`                                                           |
| Text dark        | `#1d2327`                                                           |
| Text muted       | `#50575e`                                                           |
| Muted / meta     | `#8c8f94`                                                           |
| Border           | `#dcdcde`                                                           |
| Admin background | `#f0f0f1`                                                           |
| Notice pill      | bg `#dbdbc5`, text `#4f5a2e`                                        |
| Font             | `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif` |

Take a real screenshot of the UI you are mocking and match against it, rather
than working from memory.

## Publishing

Uploading to the media library or setting a featured image writes to the **live
production site**. Per CLAUDE.local.md, only do that when explicitly asked.
Generating the file locally is always fine; publishing it is not.

WordPress OG tags read the **featured image**, so setting the featured image is
what actually changes the share card.
