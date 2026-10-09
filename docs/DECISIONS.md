# Decisions

## 2026-10-09

- Cache purges target every `?v=` variant a visitor may hold, not just the current one: `pre_post_update` records the pre-save version in `_pds_versions` (capped at 20, cleared on disable/regenerate). Rationale: `?v=` is the GMT modified time, which changes on every save and before `transition_post_status`, so purging only the current version missed the first-publish auto-disable path.
- `scheduled_purge_url()` always purges the URL stored with the cron event and adds the `?v=` variants only when that URL still matches the current token, so a missed unschedule never drops the stale URL.
- Share links reject posts in `trash`, `auto-draft`, or `inherit` status (denylist, so custom editorial statuses keep working). Treated as a direct bug fix.
- `get_route_base()` trims slashes and falls back to `pds` when empty, so rewrite rules, self-heal, and generated URLs share one value.

## 2026-05-15

- Initialized repo memory with root `AGENTS.md` plus `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, `docs/TASKS.md`, and `docs/CHANGELOG_WORK.md`.
- Keep valid shared draft rendering delegated to the active WordPress theme; this plugin owns access checks, headers, routing, and invalid/expired error pages.
- Use OpenSpec proposals before new capabilities, breaking changes, architecture changes, or security/performance behavior changes; direct bug fixes and small documentation/config updates can proceed without a proposal.
