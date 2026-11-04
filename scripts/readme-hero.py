"""Builds docs/media/hero-{light,dark}.webp from the curated screenshots:
the customer site and the kitchen display side by side, with the phone view.
python3 scripts/readme-hero.py (needs Pillow)."""
from PIL import Image, ImageDraw, ImageFilter

SCREENS = 'docs/media/screens/'
THEMES = {'light': (247, 241, 232), 'dark': (22, 17, 13)}


def framed(path, width, radius):
    shot = Image.open(path).convert('RGB')
    shot = shot.resize((width, round(shot.height * width / shot.width)), Image.LANCZOS)
    mask = Image.new('L', shot.size, 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, *shot.size), radius, fill=255)
    return shot, mask


def place(canvas, path, x, y, width, radius):
    shot, mask = framed(path, width, radius)
    shadow = Image.new('RGBA', canvas.size, (0, 0, 0, 0))
    block = Image.new('RGBA', shot.size, (0, 0, 0, 120))
    shadow.paste(block, (x, y + 18), mask)
    canvas.alpha_composite(shadow.filter(ImageFilter.GaussianBlur(24)))
    canvas.paste(shot, (x, y), mask)
    ImageDraw.Draw(canvas).rounded_rectangle(
        (x, y, x + shot.width, y + shot.height), radius, outline=(128, 118, 108, 90), width=2
    )


for theme, background in THEMES.items():
    canvas = Image.new('RGBA', (1800, 900), background + (255,))
    place(canvas, f'{SCREENS}home-desktop-{theme}.webp', 50, 70, 1040, 22)
    place(canvas, f'{SCREENS}kitchen-display-desktop-{theme}.webp', 720, 250, 1040, 22)
    place(canvas, f'{SCREENS}menu-phone-{theme}.webp', 110, 530, 330, 40)
    canvas.convert('RGB').save(f'docs/media/hero-{theme}.webp', quality=86, method=6)
