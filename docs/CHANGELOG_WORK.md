# Work Changelog

## 2026-05-15

- Ran `project-memory` setup for this repository.
- Added docs-based repo memory files under `docs/` and refreshed `AGENTS.md` with the project memory requirement.
- Migrated the old local root `TASKS.md` checklist into `docs/TASKS.md` and condensed it into current follow-ups, manual QA, and historical completed items.
- Populated `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, and `docs/CHANGELOG_WORK.md` from repo evidence: `README.md`, `readme.txt`, `composer.json`, `public-draft-share.php`, `includes/`, `assets/admin.js`, `uninstall.php`, recent git history, and `openspec/`.
- Fixed review findings by allowing `docs/TASKS.md` to be tracked, removing a local absolute path from tracked docs, and excluding agent/spec/memory docs plus local-only artifacts from the release ZIP command.
