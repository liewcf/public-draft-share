# Work Changelog

## 2026-10-09 (experimental branch `fix/audit-findings`, worktree `../public-draft-share-fixes`)

- Audit of v1.0.1 (WP compatibility, security, bugs) found three correctness issues; fixes prototyped on this branch without touching `main`:
  - `54ab36b fix(core)`: `grant_read_cap()` now grants the mapped primitive caps from `map_meta_cap()` (e.g. `edit_others_posts` for drafts, `read_private_posts` for private posts) instead of the ineffective `read_post` key; also covers `read_page` and renamed CPT read caps, and never satisfies `do_not_allow`.
  - `e6d31ba fix(routing)`: rewrite self-heal in `maybe_upgrade_rewrites()` derives the rule prefix from the filtered `pds_route_base` and only runs with pretty permalinks; previously a custom route base or plain permalinks triggered `flush_rewrite_rules()` on every `admin_init`.
  - `ad0968a fix(cache)`: disable/regenerate/expiry purges now target the versioned `?v=` URL visitors actually use via a new `build_versioned_share_url()` helper; `purge_url_cache()` still strips query args to cover the raw variant.
- Verification so far: `php -l` clean; PHPCS (WPCS-Core+Extra, minus the sniff that hangs on PHP 8.5) reports only the two rules intentionally relaxed in `phpcs.xml.dist`.
- Pending before merging to `main`: manual QA on a disposable WordPress 7.1.x site (link create/disable, logged-out draft view, cap checks, custom route base, purge URLs); per `docs/DECISIONS.md` the `grant_read_cap` change touches security behavior and may warrant a short OpenSpec proposal.
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
