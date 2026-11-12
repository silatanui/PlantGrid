from __future__ import annotations

import collections
from pathlib import Path

from PIL import Image

ASSETS = Path(r"C:\Users\silat\.cursor\projects\c-xampp-apps-PlantGrid\assets")
SRC_BADGE = next(ASSETS.glob("*Teal_PlantGrid_Badge_Logo*.png"))
SRC_MARK = next(ASSETS.glob("*Teal_P_Logo_with_Slanted_Stripes*.jpg"))

OUT_DIRS = [
    Path(r"C:\xampp\apps\PlantGrid\public\images"),
    Path(r"C:\xampp\htdocs\PlantGrid\images"),
]


def knock_out_black(img: Image.Image, hard: int = 28, soft: int = 55) -> Image.Image:
    img = img.convert("RGBA")
    px = img.load()
    w, h = img.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            lum = (r + g + b) / 3
            if lum <= hard:
                px[x, y] = (r, g, b, 0)
            elif lum < soft and r < 40 and g < 50 and b < 55:
                alpha = int(255 * (lum - hard) / (soft - hard))
                px[x, y] = (r, g, b, alpha)
    return img


def crop_alpha(img: Image.Image, pad: int = 6) -> Image.Image:
    bbox = img.getbbox()
    if not bbox:
        return img
    left, top, right, bottom = bbox
    left = max(0, left - pad)
    top = max(0, top - pad)
    right = min(img.width, right + pad)
    bottom = min(img.height, bottom + pad)
    return img.crop((left, top, right, bottom))


def sample_teal(img: Image.Image) -> tuple[int, int, int]:
    img = img.convert("RGB")
    colors: list[tuple[int, int, int]] = []
    for r, g, b in img.getdata():
        if r + g + b < 40:
            continue
        if r > 220 and g > 220 and b > 220:
            continue
        if b >= r and g >= r - 10:
            colors.append((r, g, b))
    if not colors:
        return (0, 87, 97)
    quantized = [((r // 8) * 8, (g // 8) * 8, (b // 8) * 8) for r, g, b in colors]
    return collections.Counter(quantized).most_common(1)[0][0]


def fit_square(img: Image.Image, size: int) -> Image.Image:
    img = img.convert("RGBA")
    src_ratio = img.width / img.height
    if src_ratio >= 1:
        nh = size
        nw = max(size, int(size * src_ratio))
    else:
        nw = size
        nh = max(size, int(size / src_ratio))
    src = img.resize((nw, nh), Image.Resampling.LANCZOS)
    left = (src.width - size) // 2
    top = (src.height - size) // 2
    return src.crop((left, top, left + size, top + size))


def main() -> None:
    print("badge", SRC_BADGE.name)
    print("mark", SRC_MARK.name)

    badge = Image.open(SRC_BADGE)
    teal = sample_teal(badge)
    print("sampled teal", "#%02x%02x%02x" % teal)

    lockup = crop_alpha(knock_out_black(badge), pad=6)
    target_h = 120
    scale = target_h / lockup.height
    lockup_sm = lockup.resize(
        (max(1, int(lockup.width * scale)), target_h),
        Image.Resampling.LANCZOS,
    )

    mark_rgba = Image.open(SRC_MARK).convert("RGBA")
    sizes = {
        "favicon-32x32.png": 32,
        "favicon-48x48.png": 48,
        "favicon.png": 32,
        "apple-touch-icon.png": 180,
    }

    for out in OUT_DIRS:
        out.mkdir(parents=True, exist_ok=True)
        lockup_sm.save(out / "logo-lockup.png", optimize=True)
        lockup_sm.save(out / "logo-source.png", optimize=True)
        fit_square(mark_rgba, 512).save(out / "logo-mark.png", optimize=True)
        for name, size in sizes.items():
            fit_square(mark_rgba, size).save(out / name, optimize=True)
        print("wrote", out)

    print("done")


if __name__ == "__main__":
    main()
