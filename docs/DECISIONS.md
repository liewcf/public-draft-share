# Decisions

## 2026-10-09

- Removed OpenSpec from the repo (`openspec/`, the `AGENTS.md` managed block, the `.gitattributes` entry). This replaces the 2026-05-15 OpenSpec proposal rule. New rule: security or behavior changes need a dated entry here (what and why) before they are merged; plain bug fixes and small docs/config updates don't. Rationale: one-maintainer plugin; the proposal/spec/archive workflow cost more than it gave. The only spec (`share-link-access`) now lives in `docs/PROJECT_CONTEXT.md` under "Share Link Access Rules".

- Release ZIPs are built with `git archive --prefix=public-draft-share/` from the release tag. Rationale: the 1.0.1 ZIP had no top-level folder, so WordPress uploads unpacked it into `public-draft-share-1.0.1/` (a second copy, not an update), and it shipped dev files (`AGENTS.md`, `composer.*`, `phpcs.xml.dist`). `.gitattributes` `export-ignore` is now the single exclusion list (adds `README.md`, `docs/`, `openspec/`).

- Cache purges target every `?v=` variant a visitor may hold, not just the current one: `pre_post_update` records the pre-save version in `_pds_versions` (capped at 20, cleared on disable/regenerate). Rationale: `?v=` is the GMT modified time, which changes on every save and before `transition_post_status`, so purging only the current version missed the first-publish auto-disable path.
- `scheduled_purge_url()` always purges the URL stored with the cron event and adds the `?v=` variants only when that URL still matches the current token, so a missed unschedule never drops the stale URL.
- Share links reject posts in `trash`, `auto-draft`, or `inherit` status (denylist, so custom editorial statuses keep working). Treated as a direct bug fix.
- The `grant_read_cap` read-permission change and the trash rejection are recorded as OpenSpec change `update-share-link-access-checks` (new `share-link-access` capability), written after implementation at the owner's request.
- `get_route_base()` trims slashes and falls back to `pds` when empty, so rewrite rules, self-heal, and generated URLs share one value.

## 2026-05-15

- Initialized repo memory with root `AGENTS.md` plus `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, `docs/TASKS.md`, and `docs/CHANGELOG_WORK.md`.
- Keep valid shared draft rendering delegated to the active WordPress theme; this plugin owns access checks, headers, routing, and invalid/expired error pages.
- *(Superseded 2026-10-09: OpenSpec removed; see the 2026-10-09 entry.)* Use OpenSpec proposals before new capabilities, breaking changes, architecture changes, or security/performance behavior changes; direct bug fixes and small documentation/config updates can proceed without a proposal.
