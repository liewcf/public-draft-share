![Public Draft Share banner](assets/banner-772x250.png)

# Public Draft Share

Create secure, shareable links so anyone can view a draft post or page without logging in. Links can expire automatically, be revoked at any time, and send noindex headers to discourage search indexing. Rendering is handled by your active theme; this plugin only controls access and headers.

## Features

- One‑click Create/Disable controls in the post editor
- Optional expiry: 1, 3, 7, 14, 30 days, or Never
- Auto‑disables the link on first publish (and purges caches)
- No login required for viewers; permission granted only to the shared post
- Sends strong no‑cache and `X‑Robots‑Tag: noindex, nofollow`
- Works with posts, pages, and public custom post types

## Requirements

- WordPress 5.8+
- PHP 7.4+

Tested up to WordPress 6.8. See `readme.txt` for WordPress.org metadata.

## Installation

1. Copy this folder to `wp-content/plugins/public-draft-share/`.
2. Activate via WP Admin → Plugins, or with WP‑CLI:

```bash
wp plugin activate public-draft-share
```

## Usage

- Open any draft (or pending/future/private) post in the editor.
- In the “Public Draft Share” meta box, click “Create Link”.
- Copy the generated URL and open it in an incognito/logged‑out window.
- Click “Disable” any time to revoke access immediately.
- Publishing the post for the first time auto‑disables the link and purges caches.

## How It Works

- Adds a pretty permalink route: `/pds/{post_id}/{token}`.
- Valid tokens temporarily grant `read_post` for that specific post.
- Shapes the main query to load the target post; your theme renders it.
- Invalid/expired links render a minimal 404‑style page with noindex headers.

## Project Structure

- `public-draft-share.php` — Plugin bootstrap, constants, hooks.
- `includes/` — PHP classes: `PDS\\Core`, `PDS\\Admin`.
- `assets/` — Admin CSS/JS and WordPress.org banner/icon assets.
- `languages/public-draft-share.pot` — Translation template.
- `uninstall.php` — Cleans plugin meta on uninstall.
- `phpcs.xml.dist` — WordPress Coding Standards ruleset.
- `readme.txt` — WordPress.org readme metadata.

## Development

- Lint PHP

```bash
phpcs -s --standard=phpcs.xml.dist .
```

- Auto‑fix

```bash
phpcbf --standard=phpcs.xml.dist .
```

- Update POT (requires WP‑CLI)

```bash
wp i18n make-pot . languages/public-draft-share.pot
```

- Run locally

  - Place the folder in `wp-content/plugins/public-draft-share/`.
  - Activate via WP Admin or `wp plugin activate public-draft-share`.

- Package ZIP (adjust version as needed)

```bash
zip -r public-draft-share-1.0.1.zip . \
  -x "*.git*" "*.zip" "vendor/**" \
     ".github/**" "wp-cli.phar" \
     "assets/banner-*.png" "assets/icon-*.png"
```

### Coding Style & Conventions

- PHP: WordPress‑Core/Docs/Extra per `phpcs.xml.dist`; target PHP 7.4+.
- Namespaces: `PDS\\` (e.g., `PDS\\Core`). New files follow `class-pds-*.php`.
- Prefixes: actions/filters/functions start with `pds_`; constants `PDS_*`.
- JS/CSS: 2‑space indent for `.js`. Admin JS assumes jQuery.

## Manual QA Checklist

- Create a draft → click “Create Link” → open link in a logged‑out/incognito window.
- Click “Disable” → link should render the plugin’s invalid/expired message.
- Verify expiry options (1/3/7/14/30 days, Never) behave as expected.
- Publish the post for the first time → link auto‑disables and caches are purged.

## Caching & Configuration

- The plugin purges common caches for the shared URL on create/disable and on save.
- If caches are sticky, enable aggressive purge:

```php
add_filter( 'pds_aggressive_cache_flush', '__return_true' );
```

## Security Notes

- Do not log tokens or paste share URLs in public threads.
- Links are shareable but sensitive; treat them like private URLs.

## Contributing

- Commits: Conventional Commits (e.g., `feat(admin-ui): …`, `fix(routing): …`).
- PRs: Include summary, rationale, test steps, and screenshots/GIFs for UI changes. Link issues.
- Releases: bump the `Version:` header, `PDS_VERSION`, and `readme.txt` Stable tag in a dedicated commit.

## Uninstall

Running the WordPress uninstall routine removes all plugin post meta and the rewrite version option. See `uninstall.php` for details.

## License

GPL‑2.0‑or‑later. See `license.txt`.

