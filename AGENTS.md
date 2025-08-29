# Repository Guidelines

## Project Structure & Module Organization
- `public-draft-share.php`: Plugin bootstrap, constants, hooks.
- `includes/`: PHP classes (`PDS\Core`, `PDS\Admin`).
- `assets/`: Admin CSS/JS and WordPress.org banner/icon assets.
- `languages/public-draft-share.pot`: Translation template.
- `uninstall.php`: Cleans plugin meta on uninstall.
- `phpcs.xml.dist`: WordPress Coding Standards ruleset.
- `readme.txt`: WordPress.org readme metadata.

## Build, Test, and Development Commands
- Run locally: place the folder in `wp-content/plugins/public-draft-share/`, then activate via WP Admin or `wp plugin activate public-draft-share`.
- Lint PHP: `phpcs -s --standard=phpcs.xml.dist .`
- Auto‑fix: `phpcbf --standard=phpcs.xml.dist .`
- Update POT: `wp i18n make-pot . languages/public-draft-share.pot`
- Package ZIP: `zip -r public-draft-share-1.0.1.zip . -x "*.git*" "*.zip" "vendor/**" ".github/**" "wp-cli.phar" "assets/banner-*.png" "assets/icon-*.png"`

## Coding Style & Naming Conventions
- **PHP**: WordPress‑Core/Docs/Extra per `phpcs.xml.dist`; target PHP 7.4+. Indent with tabs (WPCS default). Use escaping helpers and WP APIs.
- **Namespaces**: Use `PDS\` (e.g., `PDS\Core`). New files follow `class-pds-*.php`.
- **Prefixes**: Actions/filters/functions start with `pds_`; constants `PDS_*`.
- **JS/CSS**: 2‑space indent for `.js` (see `.editorconfig`). Admin JS assumes jQuery.

## Testing Guidelines
- No automated test suite. Perform manual QA:
  - Create a draft → “Create Link” → open in a logged‑out/incognito window.
  - “Disable” link returns 404‑style error page.
  - Expiry options (1/3/7/14/30 days, Never) behave as shown.
  - Publishing first time auto‑disables link and purges caches.

## Commit & Pull Request Guidelines
- **Commits**: Follow Conventional Commits (e.g., `feat(admin-ui): …`, `fix(routing): …`).
- **PRs**: Include summary, rationale, test steps, and screenshots/GIFs for UI. Link issues. If preparing a release, bump `Version:` header, `PDS_VERSION`, and `readme.txt` Stable tag in a separate commit.

## Security & Configuration Tips
- Do not log share tokens or paste share URLs in public threads.
- Links send `noindex` headers/meta but are still shareable—treat as sensitive.
- If caches are sticky, enable aggressive purge: `add_filter( 'pds_aggressive_cache_flush', '__return_true' );`.
