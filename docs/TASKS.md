# Tasks

## Current Follow-Ups

- [ ] Recommended next action: push local `main` commit `f2e35c4` if the WordPress 7.0 readme/memory update should be published to `origin`.
- [ ] Optional cache integrations: consider Cloudflare and host-specific purge hooks behind explicit filters.
- [ ] Optional release validation: verify non-canonical plain-permalink `pds_token` values return the invalid/expired page on a real WordPress site, then check disable/expiry/publish revocation through the configured cache layer if caching is release-critical.
- [ ] CI/static-analysis improvements: consider PHPStan with a baseline, Composer cache in CI, and ESLint for `assets/admin.js`.
- [ ] Add automated tests if behavior grows beyond what manual WordPress QA can safely cover.

## Manual QA Checklist

- [ ] Create a draft, click "Create Link", and open the generated URL in a logged-out/incognito window.
- [ ] Disable the link and confirm it renders the invalid/expired 404-style page.
- [ ] Verify expiry options: 1, 3, 7, 14, 30 days, and Never.
- [ ] Publish a post for the first time and confirm the link auto-disables and cache purge hooks run.
- [ ] When routing changes, check both pretty permalinks and plain permalink fallback.

## Recently Completed / Historical

- [x] WordPress 7.0 compatibility smoke test in disposable Docker: activation, AJAX create/disable, expiry options, publish auto-disable, pretty permalink route, plain permalink fallback, targeted PHPCS, and clean post-check debug log.
- [x] Fixed Codex Security P2 token alias finding: non-canonical raw `pds_token` values are rejected before comparison while canonical tokens still validate.
- [x] Plain permalink fallback for `index.php?pds_post={id}&pds_token={token}`.
- [x] Localized admin JS labels and Clipboard API copy fallback.
- [x] Filterable expiry options and default expiry handling in PHP and JS.
- [x] `wp_head` noindex meta backup for PDS views.
- [x] CSP handling that preserves existing directives while adding `frame-ancestors`.
- [x] Scheduled cache purge on expiry and cache purge on disable/regenerate.
- [x] Capability, route-base, password-protected-post, and public-post-type filters/guards.
- [x] README filter documentation, permalink examples, admin docblock cleanup, and token-length clamp.
- [x] Project memory setup migrated the old root `TASKS.md` into this docs-based memory file.
