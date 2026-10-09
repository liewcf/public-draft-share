## 1. Implementation
- [x] 1.1 Grant mapped basic capabilities for read checks on the shared post in `grant_read_cap()`; never grant `do_not_allow` (`54ab36b`)
- [x] 1.2 Recognize `read_post`, `read_page`, and the post type's renamed read capability (`is_read_cap_for_shared_post()`)
- [x] 1.3 Reject `trash`, `auto-draft`, and `inherit` posts in `detect_pds_request()` (`509af29`)

## 2. Verification
- [x] 2.1 WP 7.1.3 Docker QA: anonymous viewer on a valid link gets `read_post=true`, `edit_post=false` (default and custom route base)
- [x] 2.2 Trashed post link returns the 404 page with no content; restoring to draft makes the link work again
- [x] 2.3 Scope check on a valid link for draft A: `read_post(A)=true`, `edit_post(A)=false`, `read_post(B)=false` for another draft B
- [x] 2.4 Regression: drafts not reachable without a token, invalid token returns 404, clean debug log

## 3. Archive
- [ ] 3.1 After the next release ships, archive this change and move the requirements into `openspec/specs/share-link-access/spec.md`
