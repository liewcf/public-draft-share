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

- Adds a pretty permalink route: `/pds/{post_id}/{token}` (or query-style for plain permalinks).
- Valid tokens temporarily grant `read_post` for that specific post.
- Shapes the main query to load the target post; your theme renders it.
- Invalid/expired links render a minimal 404‑style page with noindex headers.

### Permalink Structure

The plugin supports both pretty and plain permalink structures:

**Pretty Permalinks** (default):
```
https://example.com/pds/123/AbCdEfGh12345678
```

**Plain Permalinks** (fallback):
```
https://example.com/index.php?pds_post=123&pds_token=AbCdEfGh12345678
```

The plugin automatically detects your site's permalink structure and generates the appropriate URL format.

## Available Filters

### Expiry Configuration

**`pds_expiry_days_options`**  
Customize the available expiry day options in the dropdown.

```php
// Example: Add 60-day option and remove 14-day option
add_filter( 'pds_expiry_days_options', function ( $days ) {
    return array( 1, 3, 7, 30, 60, 0 ); // 0 = Never
} );
```

**`pds_default_expiry_days`**  
Set the default expiry period (in days).

```php
// Default to 30 days instead of 7
add_filter( 'pds_default_expiry_days', function ( $default ) {
    return 30;
} );
```

### Access Control

**`pds_can_share_post`**  
Gate who can create/disable share links beyond the default `edit_post` capability.

```php
// Example: Only allow admins to share drafts
add_filter( 'pds_can_share_post', function ( $can, $post_id ) {
    return current_user_can( 'manage_options' );
}, 10, 2 );
```

**`pds_allow_passworded`**  
Allow password-protected posts to be shared (disabled by default).

```php
// Enable sharing of password-protected posts
add_filter( 'pds_allow_passworded', '__return_true' );
```

### URL & Routing

**`pds_route_base`**  
Customize the URL route base (default: `pds`).

```php
// Change route from /pds/ to /preview/
add_filter( 'pds_route_base', function ( $base ) {
    return 'preview';
} );
// Result: https://example.com/preview/123/token
```

**Note:** After changing the route base, flush permalinks via Settings → Permalinks or `wp rewrite flush`.

### Security

**`pds_strict_csp`**  
Enable strict Content Security Policy that blocks scripts on shared views.

```php
// Enable strict CSP to reduce XSS risk
add_filter( 'pds_strict_csp', '__return_true' );
```

### Publishing Behavior

**`pds_auto_expire_on_publish`**  
Control whether links auto-disable when a post is first published.

```php
// Keep links active after publishing
add_filter( 'pds_auto_expire_on_publish', '__return_false' );
```

### Caching

**`pds_aggressive_cache_flush`**  
Enable aggressive cache flushing for stubborn caching setups.

```php
// Aggressively flush all caches when links change
add_filter( 'pds_aggressive_cache_flush', '__return_true' );
```

## Actions

**`pds_purge_expired_url`**  
Fires when a scheduled cache purge runs after link expiry.

```php
// Example: Log when links expire
add_action( 'pds_purge_expired_url', function ( $url, $post_id ) {
    error_log( "PDS link expired for post {$post_id}: {$url}" );
}, 10, 2 );
```

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

The plugin automatically purges common caches (WP Rocket, LiteSpeed, W3 Total Cache, etc.) for shared URLs on create/disable and on save. Expired links also schedule automatic cache purging.

For advanced configuration, see the [Available Filters](#available-filters) section above.

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

