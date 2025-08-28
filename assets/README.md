WordPress.org Plugin Directory Assets
====================================

These images are generated placeholders for your plugin page on WordPress.org.

Included files
- `banner-772x250.png` (1x)
- `banner-1544x500.png` (2x)
- `icon-128x128.png` (1x)
- `icon-256x256.png` (2x)

Regenerate
- Edit and run `php assets/generate-assets.php` to recreate placeholders.

Submit to .org SVN
1. In your plugin SVN checkout, place these PNGs in the top-level `assets/` directory (sibling to `trunk/`).
2. Do not include them inside the plugin ZIP; WordPress.org reads from the SVN `assets/` folder.
3. Commit and wait a few minutes for the images to appear on your plugin page.

Design tips
- Keep text large and legible; avoid tiny UI screenshots.
- Safe area (banners): avoid the extreme edges; WordPress may crop on mobile.
- Keep icons simple; high contrast works best at 128×128.

