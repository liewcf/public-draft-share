## Context
Valid share links let anonymous visitors view non-public posts. The theme renders the post through the main query, which the plugin short-circuits. Other code (themes, plugins, core template tags) may still call `current_user_can( 'read_post', $id )`. WordPress resolves that through `map_meta_cap()` into basic capabilities, which are what `user_has_cap` must grant.

## Goals / Non-Goals
- Goals: read checks for the shared post pass on a valid link; nothing else gets broader access; links stop working for posts that are not meant to be reachable.
- Non-Goals: changing how tokens are issued or validated; granting edit or other non-read access; REST API access (REST requests are served before the share context is set).

## Decisions
- Decision: In `user_has_cap`, when the share context is set, `$args[2]` is the shared post ID, and `$args[0]` is a read meta capability (`read_post`, `read_page`, or the post type's `cap->read_post`), grant each capability in `$caps` (the mapped list) except `do_not_allow`.
  - The grant applies to that single `has_cap()` call only: WordPress filters a local copy of `allcaps` per check, so nothing persists. An `edit_post` check on the same post still fails even though `edit_others_posts` may appear in a read check's mapped list.
- Decision: Reject `trash`, `auto-draft`, and `inherit` with a denylist, not an allowlist, so custom editorial statuses keep working.
- Alternatives considered:
  - Keep granting the meta capability name: does nothing, which is the bug being fixed.
  - Use a `map_meta_cap` filter to map `read_post` to `read` for the shared post: also works, but changes the mapping for every caller in the request and is harder to scope to read checks only.
  - Allowlist of statuses (`draft`, `pending`, `future`, `private`, `publish`): would break plugins that add editorial statuses.

## Risks / Trade-offs
- A read check's mapped list can contain powerful-sounding capabilities (`edit_others_posts`, `read_private_posts`). Mitigation: only granted when the requested capability is a read capability for the shared post, and only for that call. QA confirms `edit_post` stays false for anonymous viewers.
- Code that checks a basic capability directly (for example `current_user_can( 'edit_others_posts' )` with no post ID) is unaffected, because `$args[2]` is missing.

## Migration Plan
No data migration. Rollback is reverting the two commits.

## Open Questions
- None.
