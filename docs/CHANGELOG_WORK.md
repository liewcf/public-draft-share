# Work Changelog

## 2026-10-09 (branch `chore/maintenance`)

- `composer.lock`: `composer update --with-all-dependencies` fixes the 3 high Dependabot alerts (all dev-only lint tooling): `squizlabs/php_codesniffer` 3.13.6, `wp-coding-standards/wpcs` 3.4.1, `phpcsstandards/phpcsutils` 1.2.3. `composer audit` clean.
- `phpcs.xml.dist`: added `uninstall.php`, excluded `/vendor/*`, limited to `.php`. With updated tooling PHPCS runs on PHP 8.5 without hanging. The old crash came from passing `.` (as `composer lint` and the `AGENTS.md` command do), which scanned `vendor/` test fixtures; `admin.js` was also being sniffed against PHP rules.
- `assets/index.php`: docblock file comment so it passes WPCS.
- `public-draft-share.php`: Plugin URI now `https://github.com/liewcf/public-draft-share`. `readme.txt`: `Contributors: liewcf`.
- `languages/public-draft-share.pot`: regenerated with WP-CLI in the Docker QA container (`--exclude=vendor,docs,openspec`); adds missing `day`/`days` strings, refreshes line refs and Plugin URI.
- Verification: `composer lint` and `vendor/bin/phpcs --standard=phpcs.xml.dist` report 0 errors/warnings.

## 2026-10-09 (experimental branch `fix/audit-findings`, worktree `../public-draft-share-fixes`)

- Audit of v1.0.1 (WP compatibility, security, bugs) found three correctness issues; fixes prototyped on this branch without touching `main`:
  - `54ab36b fix(core)`: `grant_read_cap()` now grants the mapped primitive caps from `map_meta_cap()` (e.g. `edit_others_posts` for drafts, `read_private_posts` for private posts) instead of the ineffective `read_post` key; also covers `read_page` and renamed CPT read caps, and never satisfies `do_not_allow`.
  - `e6d31ba fix(routing)`: rewrite self-heal in `maybe_upgrade_rewrites()` derives the rule prefix from the filtered `pds_route_base` and only runs with pretty permalinks; previously a custom route base or plain permalinks triggered `flush_rewrite_rules()` on every `admin_init`.
  - `ad0968a fix(cache)`: disable/regenerate/expiry purges now target the versioned `?v=` URL visitors actually use via a new `build_versioned_share_url()` helper; `purge_url_cache()` still strips query args to cover the raw variant.
- Verification: `php -l` clean; PHPCS (WPCS-Core+Extra, minus the sniff that hangs on PHP 8.5) reports only the two rules intentionally relaxed in `phpcs.xml.dist`.
- 2026-10-09 Docker QA on WordPress 7.1.3 / PHP 8.3.35 / MySQL 8.4 (disposable compose stacks: fixed branch vs `main`, instrumented mu-plugin, real HTTP flows incl. admin-ajax disable with nonce): fixed branch passes 19/19 checks; old code reproduces all three bugs — anonymous `read_post=false` on valid links (B1), disable purges only the raw URL missing the `?v=` variant visitors use (B3), and with a custom `pds_route_base` every wp-admin page load triggers a full `flush_rewrite_rules()` (rule regeneration + `.htaccess` write; measured 1 flush per admin page on old vs 0 on fixed) (B2). Regression checks (draft render logged-out, invalid-token 404, draft not exposed without token, scheduled-post sharing, ajax disable, admin loads, clean debug log) pass identically on both versions.
- QA harness kept outside the repo in a local temp dir (`pds-wp-test/`: docker-compose.yml, qa.sh, mu-plugins/pds-qa.php). Gotchas learned: wp-cli sends `error_log()` to stderr (WP_DEBUG_LOG is false in CLI), so instrumented events must be triggered over HTTP; WP 7.1 `refresh_rewrite_rules()` short-circuits `update_option` when rules are unchanged, so flush counting must use the `flush_rewrite_rules_hard` filter; drafts have zero `post_modified_gmt`, so `?v=` only appears on future/published-then-scheduled posts.
- Review follow-ups (`1aade52 fix(routing)`, `509af29 fix(core)`, `5f1803a fix(cache)`), `includes/class-pds-core.php` + `uninstall.php`:
  - Versioned purge now covers past `?v=` values: new `remember_version()` on `pre_post_update` stores pre-save versions in `_pds_versions`; `get_share_url_variants()`/`purge_share_link()` purge raw + current + remembered URLs on disable, regenerate, save, and first publish. `purge_url_cache()` replaced by multi-URL `purge_urls()`. `uninstall.php` deletes `_pds_versions`.
  - `scheduled_purge_url()` keeps the cron-stored URL and adds variants only when it matches the current token.
  - `detect_pds_request()` rejects `trash`, `auto-draft`, `inherit` posts.
  - `get_route_base()` trims slashes / falls back to `pds`; `build_share_url_raw()` no longer trims separately.
  - Verification: `php -l` clean; Docker WP 7.1.3 fresh stack `qa.sh` 19/19 pass; new `qa-followups.sh` (same local harness dir) 20/20 pass: original + current `?v=` purged on disable, pre-publish URL purged on `wp_update_post` publish and on cron `wp_publish_post`, trashed link 404 with no content leak and restore works, stale scheduled URL purged without touching current token, `/preview/` base normalized with `^preview/` rule, clean debug log. PHPCS not run (not installed).
- Pending before merging to `main`: per `docs/DECISIONS.md` the `grant_read_cap` change touches security behavior and may warrant a short OpenSpec proposal; bump `Tested up to: 7.1` alongside (WP 7.1.3 QA now green).
- Remaining audit findings not yet addressed: bump `Tested up to` to 7.1 after re-test, placeholder Plugin URI, `readme.txt` Contributors slug, stale POT line refs, lint tooling incompatible with PHP 8.5.

## 2026-05-26

- Fixed the Codex Security P2 token-canonicalization finding by rejecting non-canonical raw `pds_token` values before token comparison instead of stripping invalid characters.
- Added and ran a focused real-method regression harness under the scan artifact bundle to verify canonical tokens still work and punctuation/newline alias tokens are rejected.
- Verified plugin compatibility on WordPress 7.0 in a disposable Docker site using `wordpress:7.0-php8.3-apache`, `wordpress:cli-php8.3`, and `mysql:8.4`.
- Activated the plugin, exercised AJAX link creation/disable, checked expiry options `1/3/7/14/30/Never`, verified first-publish auto-disable, and tested both pretty permalink and plain permalink share URLs as logged-out HTTP requests.
- Ran targeted PHP syntax checks and PHPCS for the WordPress 7.0 compatibility check; no plugin code changes were required for that check.
- Cleared the disposable WordPress debug log, reran representative checks, and confirmed it stayed empty.
- Updated `README.md` and `readme.txt` to document WordPress 7.0 compatibility verification.
- Committed the readme and memory updates locally as `f2e35c4 docs(readme): document WordPress 7.0 compatibility`; `origin/main` was still at `fe7fc0b` after the commit.

## 2026-05-15

- Ran `project-memory` setup for this repository.
- Added docs-based repo memory files under `docs/` and refreshed `AGENTS.md` with the project memory requirement.
- Migrated the old local root `TASKS.md` checklist into `docs/TASKS.md` and condensed it into current follow-ups, manual QA, and historical completed items.
- Populated `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, and `docs/CHANGELOG_WORK.md` from repo evidence: `README.md`, `readme.txt`, `composer.json`, `public-draft-share.php`, `includes/`, `assets/admin.js`, `uninstall.php`, recent git history, and `openspec/`.
- Fixed review findings by allowing `docs/TASKS.md` to be tracked, removing a local absolute path from tracked docs, and excluding agent/spec/memory docs plus local-only artifacts from the release ZIP command.
