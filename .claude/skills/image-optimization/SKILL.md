---
name: image-optimization
description: Compresses PNG, JPEG, and GIF images before they are committed (pngquant + oxipng pipeline, jpegoptim/mozjpeg, vips, gifsicle). Use when adding or updating any image in the repo, such as wordpress.org screenshots, banners, teaser images, or OG share cards.
---

# Image optimization

Every PNG committed to the repo — wordpress.org screenshots and banners, teaser images, OG share cards, avatars, icons — goes through compression before it lands. These files ship to users and are served from wordpress.org's CDN, so bytes matter.

Two-step pipeline, always in this order:

```bash
# 1. Lossy quantization to an 8-bit palette (usually 60-70% of the win).
pngquant --quality=80-95 --strip --skip-if-larger --force --ext .png <file>.png

# 2. Lossless recompression on top.
oxipng -o max --strip safe <file>.png
```

`--skip-if-larger` means step 1 is a no-op when quantization would not help, so the pair is safe to run on anything. Both steps are idempotent — re-running on an already-optimized file changes nothing.

For a whole directory:

```bash
pngquant --quality=80-95 --strip --skip-if-larger --force --ext .png *.png
oxipng -o max --strip safe *.png
```

Check the result and mention the saving when reporting back:

```bash
ls -lh *.png
```

If a screenshot has fine gradients or text that visibly degrades at `80-95`, raise the floor (`--quality=90-100`) rather than dropping step 1 entirely. Do not commit a PNG straight out of Playwright or an image editor.

## Other Formats

-   **JPEG** — `jpegoptim --strip-all -m85 <file>.jpg`, or `/opt/homebrew/opt/mozjpeg/bin/cjpeg -quality 82 -optimize -progressive` when encoding from source (mozjpeg is keg-only, so it is not on `PATH`).
-   **Resizing / format conversion** — `vipsthumbnail in.png --size 1200x -o out.png`. `vips` is the fast batch workhorse and uses mozjpeg for JPEG output automatically.
-   **GIF** — `gifsicle -O3 --lossy=80 in.gif -o out.gif`.
-   **WebP / AVIF** — `cwebp -q 80` and `avifenc` are available, but wordpress.org assets must stay PNG/JPEG.

See CLAUDE.local.md for which of these are installed locally.
