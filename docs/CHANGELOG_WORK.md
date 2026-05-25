# Work Changelog

## 2026-05-26

- Verified plugin compatibility on WordPress 7.0 in a disposable Docker site using `wordpress:7.0-php8.3-apache`, `wordpress:cli-php8.3`, and `mysql:8.4`.
- Activated the plugin, exercised AJAX link creation/disable, checked expiry options `1/3/7/14/30/Never`, verified first-publish auto-disable, and tested both pretty permalink and plain permalink share URLs as logged-out HTTP requests.
- Ran targeted PHP syntax checks and PHPCS for `public-draft-share.php`, `includes/`, and `uninstall.php`; no plugin code changes were required.
- Cleared the disposable WordPress debug log, reran representative checks, and confirmed it stayed empty.
- Updated `README.md` and `readme.txt` to document WordPress 7.0 compatibility verification.

## 2026-05-15

- Ran `project-memory` setup for this repository.
- Added docs-based repo memory files under `docs/` and refreshed `AGENTS.md` with the project memory requirement.
- Migrated the old local root `TASKS.md` checklist into `docs/TASKS.md` and condensed it into current follow-ups, manual QA, and historical completed items.
- Populated `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, and `docs/CHANGELOG_WORK.md` from repo evidence: `README.md`, `readme.txt`, `composer.json`, `public-draft-share.php`, `includes/`, `assets/admin.js`, `uninstall.php`, recent git history, and `openspec/`.
- Fixed review findings by allowing `docs/TASKS.md` to be tracked, removing a local absolute path from tracked docs, and excluding agent/spec/memory docs plus local-only artifacts from the release ZIP command.
