# Project Context

## Overview

- Project purpose: Public Draft Share is a WordPress plugin that creates secure, revocable public links for unpublished posts/pages so reviewers can view drafts without logging in.
- Primary users: WordPress site editors/admins who need to share drafts externally.
- Current status: Version 1.0.1 plugin source with WordPress.org-style `readme.txt`, GitHub `README.md`, WPCS config, admin assets, translation POT, and release/package guidance.

## Architecture

- `public-draft-share.php` bootstraps constants, loads translations, registers `PDS\Core` and `PDS\Admin`, and flushes rewrite rules on activation/deactivation.
- `includes/class-pds-core.php` owns token generation, `/pds/{post_id}/{token}` and plain-permalink routing, request validation, query shaping, temporary `read_post` access, no-cache/noindex/security headers, cache purges, expiry cleanup, and auto-disable on first publish.
- `includes/class-pds-admin.php` owns the editor meta box, localized jQuery admin script data, AJAX create/disable handlers, expiry options, capability checks, and admin UI rendering.
- `assets/admin.js` updates the meta box with jQuery, calls `wp_ajax_pds_generate` / `wp_ajax_pds_disable`, and copies links using Clipboard API with `execCommand` fallback.
- `uninstall.php` deletes `_pds_token`, `_pds_expires`, and `pds_rewrite_version` across single-site or multisite installs.
- `openspec/` is present for proposal-driven changes; feature, breaking, architecture, security, and performance-behavior changes should follow the OpenSpec gate before implementation.

## Development Workflow

- Package manager: Composer for development tooling only.
- Lint command: `composer run lint` or `phpcs -s --standard=phpcs.xml.dist .`
- Auto-fix command: `composer run fix` or `phpcbf --standard=phpcs.xml.dist .`
- POT command: `wp i18n make-pot . languages/public-draft-share.pot`
- Run locally: copy the repo folder to `wp-content/plugins/public-draft-share/`, then activate in WP Admin or with `wp plugin activate public-draft-share`.
- Package command: `zip -r public-draft-share-1.0.1.zip . -x "*.git*" "*.zip" ".DS_Store" "vendor/" "vendor/**" ".github/" ".github/**" "wp-cli.phar" "AGENTS.md" "docs/" "docs/**" "openspec/" "openspec/**" "output/" "output/**" "tmp/" "tmp/**" "assets/banner-*.png" "assets/icon-*.png"`
- Automated test suite: none currently; rely on linting plus manual WordPress QA.

## Manual QA

- Create a draft, click "Create Link", and open the generated URL logged out or in an incognito window.
- Disable the link and confirm it renders the plugin's invalid/expired 404-style page.
- Check expiry choices: 1, 3, 7, 14, 30 days, and Never.
- Publish a post for the first time and confirm the share link auto-disables and cache purge hooks run.
- Verify both pretty permalink URLs and plain permalink fallback URLs when changing routing behavior.

## Coding Conventions

- PHP targets WordPress 5.8+ and PHP 7.4+.
- Follow WordPress-Core/Docs/Extra rules from `phpcs.xml.dist`; PHP uses WPCS tab indentation.
- Use namespace `PDS\`; new classes should follow `class-pds-*.php`.
- Prefix plugin hooks/functions with `pds_`; constants use `PDS_*`.
- JS/CSS are admin-only assets; JS uses jQuery and 2-space indentation.
- Use WordPress escaping, sanitization, nonce, capability, post meta, cron, rewrite, and i18n APIs.

## Constraints

- Do not log or publicly paste share tokens/share URLs; links are sensitive even though `noindex` headers/meta are emitted.
- Preserve active-theme rendering for valid public draft views; the plugin controls access, headers, and error rendering.
- Keep changes surgical and WordPress-compatible; do not introduce a build pipeline unless required.
- Release version bumps must update the plugin header `Version:`, `PDS_VERSION`, and `readme.txt` Stable tag together, preferably in a separate commit.
- If caches are sticky, the documented opt-in is `add_filter( 'pds_aggressive_cache_flush', '__return_true' );`.
- Root `AGENTS.md` is the short instruction file; durable repo memory lives in `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, `docs/TASKS.md`, and `docs/CHANGELOG_WORK.md`.
