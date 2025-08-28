=== Public Draft Share ===
Contributors: Liew Cheon Fong
Tags: preview, share, draft, link, private, token
Requires at least: 5.8
Tested up to: 8.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create secure, shareable links for viewing draft posts and pages without logging in.

== Description ==

Public Draft Share lets you generate a unique, unguessable URL to share a draft (or pending/future/private) post with anyone—no account required. You can set an optional expiry, revoke the link at any time, and the page is marked `noindex` to prevent search engines from indexing it.

Features:
* One‑click Create/Regenerate/Disable from the post editor
* Optional expiry (1, 3, 7, 14, 30 days, or never)
* No login required for viewers
* Noindex headers and meta to discourage indexing
* Works with posts, pages, and public custom post types

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/public-draft-share/`.
2. Activate the plugin through the “Plugins” screen in WordPress.
3. Edit any draft post/page → find the “Public Draft Share” meta box → click “Create Link”.

== Frequently Asked Questions ==

= Can I revoke a link? =
Yes. Click “Disable” in the meta box to immediately revoke access.

= Does this affect SEO? =
The shared page sends `X-Robots-Tag: noindex, nofollow` and adds a `<meta name="robots" ...>` tag to discourage indexing.

= What happens on uninstall? =
All link tokens and expiry metadata are removed from the database.

== Screenshots ==
1. Meta box with create/regenerate/disable controls

== Changelog ==

= 1.0.0 =
* Initial release.

