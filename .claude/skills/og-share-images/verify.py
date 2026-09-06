#!/usr/bin/env python3
"""Check a rendered OG share card against the platform specs.

Usage:  python3 verify.py path/to/card.png

Checks dimensions, aspect ratio, alpha, file size, whether X's ~2:1 crop
would clip anything, and prints the legibility table for a 400px-wide
mobile share card. Exits non-zero if any hard check fails.
"""

import os
import struct
import sys

# Sizes used in the 1200-wide design space, for the legibility table.
# Update these to match the card being checked.
DESIGN_ELEMENTS = [
    ("headline", 50),
    ("eyebrow badge", 23),
    ("card title", 26),
    ("reaction emoji", 29),
    ("reaction count", 24),
    ("brand", 21),
]

MOBILE_CARD_WIDTH = 400
DESIGN_WIDTH = 1200
LEGIBILITY_FLOOR = 8.0


def png_header(path):
    with open(path, "rb") as fh:
        head = fh.read(33)

    if head[:8] != b"\x89PNG\r\n\x1a\n":
        sys.exit(f"{path} is not a PNG")

    width, height = struct.unpack(">II", head[16:24])
    return width, height, head[24], head[25]


def main():
    if len(sys.argv) != 2:
        sys.exit(__doc__)

    path = sys.argv[1]
    if not os.path.exists(path):
        sys.exit(f"no such file: {path}")

    width, height, depth, colour_type = png_header(path)
    size_kb = os.path.getsize(path) / 1024
    ratio = width / height
    has_alpha = colour_type in (4, 6)

    print(f"{path}\n")
    print(f"  dimensions : {width} x {height}")
    print(f"  ratio      : {ratio:.3f}:1")
    print(f"  colour     : {depth}-bit type {colour_type} (alpha: {'yes' if has_alpha else 'no'})")
    print(f"  file size  : {size_kb:.0f} KB")

    failures = []

    if (width, height) != (2400, 1260):
        failures.append(f"expected 2400x1260, got {width}x{height}")

    if abs(ratio - 1.905) > 0.02:
        failures.append(f"ratio {ratio:.3f} is not ~1.905 (1.91:1)")

    if has_alpha:
        failures.append("has an alpha channel — renders black on some platforms")

    if size_kb > 5120:
        failures.append(f"{size_kb:.0f} KB exceeds X's 5 MB limit")

    # X renders summary_large_image nearer 2:1 and trims top and bottom.
    trim = (height - width / 2) / 2
    print(f"\n  X crops ~2:1, trimming {trim:.0f}px top and bottom")

    try:
        from PIL import Image
    except ImportError:
        print("    (install Pillow to check the crop bands for content)")
    else:
        image = Image.open(path).convert("RGB")

        def content_pixels(y0, y1):
            return sum(
                1
                for y in range(int(y0), int(y1))
                for x in range(0, width, 4)
                if (lambda p: abs(p[0] - p[2]) > 28 or p[0] < 200)(image.getpixel((x, y)))
            )

        top = content_pixels(0, trim)
        bottom = content_pixels(height - trim, height)
        print(f"    top band    : {top} content pixels")
        print(f"    bottom band : {bottom} content pixels")

        if top or bottom:
            failures.append("content sits inside X's crop bands and will be clipped")

    scale = MOBILE_CARD_WIDTH / DESIGN_WIDTH
    print(f"\n  legibility on a {MOBILE_CARD_WIDTH}px mobile card:")
    for name, px in DESIGN_ELEMENTS:
        rendered = px * scale
        verdict = "ok" if rendered >= LEGIBILITY_FLOOR else "decorative only"
        print(f"    {name:16} {px:>3}px -> {rendered:5.1f}px   {verdict}")
    print(f"\n    Anything carrying meaning or humour needs >= {LEGIBILITY_FLOOR}px here.")

    if failures:
        print("\nFAILED:")
        for failure in failures:
            print(f"  - {failure}")
        sys.exit(1)

    print("\nAll checks passed.")


if __name__ == "__main__":
    main()
