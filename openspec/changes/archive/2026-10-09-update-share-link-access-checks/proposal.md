# Change: Grant real read access for valid share links and block unreachable post states

## Why
`grant_read_cap()` set `$allcaps['read_post']`, but `read_post` is a meta capability that `map_meta_cap()` turns into basic capabilities (for example `edit_others_posts` for drafts). So anonymous visitors on a valid link always failed `current_user_can( 'read_post', $id )`, and any theme or plugin code that checked it treated the shared draft as hidden. Separately, share links kept working after a post was moved to the trash, because the token meta survives trashing.

## What Changes
- On a request with a valid share link, `read_post` / `read_page` (and a custom post type's renamed read capability) checks for the shared post pass by granting the mapped basic capabilities for that check only. `do_not_allow` is never granted. Other capabilities and other posts are unaffected.
- Share links are rejected (normal invalid/expired 404 page) when the post status is `trash`, `auto-draft`, or `inherit`. Restoring the post restores the link.

## Impact
- Affected specs: `share-link-access` (new capability)
- Affected code: `includes/class-pds-core.php` (`grant_read_cap()`, `is_read_cap_for_shared_post()`, `detect_pds_request()`)
- Already implemented on branch `fix/audit-findings` (`54ab36b`, `509af29`); this proposal records the security behavior for review.
