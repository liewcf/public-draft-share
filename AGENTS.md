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
- Package ZIP (from the release tag; dev files excluded via `.gitattributes` `export-ignore`): `git archive --format=zip --prefix=public-draft-share/ -o public-draft-share-1.0.2.zip v1.0.2`

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
- **Security/behavior changes**: Add a dated entry to `docs/DECISIONS.md` (what and why) before merging; check the share-link access rules in `docs/PROJECT_CONTEXT.md`. Plain bug fixes restoring intended behavior and small docs/config updates don't need one.
- **PRs**: Include summary, rationale, test steps, and screenshots/GIFs for UI. Link issues. If preparing a release, bump `Version:` header, `PDS_VERSION`, and `readme.txt` Stable tag in a separate commit.

## Security & Configuration Tips
- Do not log share tokens or paste share URLs in public threads.
- Links send `noindex` headers/meta but are still shareable—treat as sensitive.
- If caches are sticky, enable aggressive purge: `add_filter( 'pds_aggressive_cache_flush', '__return_true' );`.

## Project Memory Requirement

Keep these repo-level memory files accurate and concise when work changes project context:

- `docs/PROJECT_CONTEXT.md` for stable project facts, architecture, workflows, and constraints.
- `docs/DECISIONS.md` for dated technical or product decisions and rationale.
- `docs/TASKS.md` for current tasks, blockers, and next actions.
- `docs/CHANGELOG_WORK.md` for dated notes on changed files, behavior, docs, config, dependencies, tooling, tests, and verification.

Do not store secrets, credentials, API keys, private tokens, database dumps, or sensitive personal data in project memory.
