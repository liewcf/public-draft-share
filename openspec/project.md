# Project Context

## Purpose
Public Draft Share is a WordPress plugin that lets admins generate shareable,
time-bound links to view draft posts without login, while keeping those links
private and easy to revoke.

## Tech Stack
- PHP 7.4+ (WordPress plugin)
- WordPress Core APIs (hooks, admin UI, post meta)
- JavaScript (jQuery-based admin UI)
- CSS for admin styling
- PHPCS with WordPress Coding Standards

## Project Conventions

### Code Style
- PHP follows WordPress-Core/Docs/Extra per `phpcs.xml.dist`
- Indentation: tabs for PHP (WPCS default), 2 spaces for JS/CSS
- Namespaces: `PDS\` (e.g., `PDS\Core`, `PDS\Admin`)
- File naming: `class-pds-*.php` for classes
- Prefixes: actions/filters/functions start with `pds_`; constants with `PDS_`
- Use WordPress escaping helpers and standard WP APIs

### Architecture Patterns
- `public-draft-share.php` is the bootstrap for constants and hooks
- `includes/` contains PHP classes (`PDS\Core`, `PDS\Admin`)
- `assets/` contains admin JS/CSS and wp.org assets
- `uninstall.php` handles cleanup of plugin data on uninstall

### Testing Strategy
- No automated test suite; rely on manual QA flows:
- Create a draft → "Create Link" → open in logged-out/incognito window
- "Disable" link should return 404-style error page
- Expiry options (1/3/7/14/30 days, Never) behave as shown
- Publishing first time auto-disables link and purges caches

### Git Workflow
- Conventional Commits (e.g., `feat(admin-ui): ...`, `fix(routing): ...`)
- PRs include summary, rationale, test steps, and screenshots/GIFs for UI
- Release prep: bump `Version:` header, `PDS_VERSION`, and `readme.txt`
  Stable tag in a separate commit

## Domain Context
- Share links are sensitive; do not log or expose tokens
- Links send `noindex` headers/meta but remain shareable
- Caching can require aggressive purge via
  `add_filter( 'pds_aggressive_cache_flush', '__return_true' );`

## Important Constraints
- Must remain compatible with PHP 7.4+ and WordPress APIs
- Avoid exposing share URLs or tokens in logs or public output
- Follow WordPress Coding Standards and plugin conventions

## External Dependencies
- WordPress Core (admin UI, hooks, post meta, routing)
- WP-CLI for i18n POT generation (`wp i18n make-pot`)
- PHPCS with WordPress Coding Standards
