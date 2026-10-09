# share-link-access Specification

## Purpose
Defines what a valid share link lets an anonymous visitor access, and when a stored token must stop working.

## Requirements
### Requirement: Read Access for Valid Share Links
When a request carries a valid, unexpired share link, the plugin SHALL make read capability checks for the shared post pass, including `read_post`, `read_page`, and the post type's renamed read capability. It SHALL do this by granting the basic capabilities that `map_meta_cap()` requires for that check, only for that check. It MUST NOT grant `do_not_allow`, MUST NOT affect non-read capability checks, and MUST NOT affect checks for any other post.

#### Scenario: Anonymous visitor reads a shared draft
- **WHEN** a logged-out visitor opens a valid share link for a draft
- **THEN** `current_user_can( 'read_post', $post_id )` returns true for that post

#### Scenario: Edit access is not granted
- **WHEN** a logged-out visitor opens a valid share link for a draft
- **THEN** `current_user_can( 'edit_post', $post_id )` returns false

#### Scenario: Other posts are unaffected
- **WHEN** a logged-out visitor opens a valid share link for one post
- **THEN** read checks for any other non-public post still return false

#### Scenario: Hard denials are kept
- **WHEN** `map_meta_cap()` returns `do_not_allow` for a read check on the shared post
- **THEN** the check still fails

### Requirement: Share Links Reject Unreachable Post States
The plugin SHALL treat a share link as invalid, and show the normal invalid/expired 404 page, when the shared post's status is `trash`, `auto-draft`, or `inherit`, even if the stored token matches. Other statuses, including custom editorial statuses, SHALL keep their existing behavior.

#### Scenario: Trashed post
- **WHEN** a post with an active share link is moved to the trash and the link is opened
- **THEN** the response is the 404 invalid/expired page and no post content is shown

#### Scenario: Restored post
- **WHEN** the trashed post is restored to draft and the same link is opened
- **THEN** the shared draft renders again

