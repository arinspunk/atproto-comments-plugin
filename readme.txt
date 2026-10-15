=== AT Protocol Comments ===
Contributors: arinspunk
Tags: comments, bluesky, atproto, fediverse, social
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show the replies to a Bluesky (AT Protocol) post as the comments of your posts.

== Description ==

AT Protocol Comments turns a conversation on Bluesky, or any other AT Protocol app, into the comment section of a WordPress post.

Publish your post, share it on Bluesky, and paste the link to that post in the editor. The replies appear under your post, threaded, with their authors and dates. Visitors reply from their own app; there is nothing to moderate in WordPress and no accounts to create.

* Works with block and classic themes.
* Paste an `at://` URI or the post URL from bsky.app, mu.social or any compatible app.
* Replace the native comments, or show both, one after the other.
* Choose the content types where it is active (posts by default).
* Interface and dates in your site's language.
* Scripts and styles load only on the posts that show AT Protocol comments.

The comments are displayed by the [atproto-comments](https://github.com/arinspunk/atproto-comments) web component, bundled with the plugin. Its source code is available on [GitHub](https://github.com/arinspunk/atproto-comments) and as the [atproto-comments npm package](https://www.npmjs.com/package/atproto-comments). The source of this plugin, including its block editor panel, is on [GitHub](https://github.com/arinspunk/atproto-comments-plugin).

== External services ==

This plugin connects to an AT Protocol AppView to load the replies to the linked post. By default it uses the public AppView run by Bluesky Social PBC, `https://public.api.bsky.app`. You can change it in Settings → AT Protocol Comments.

* **What is sent:** the `at://` URI of the linked post and the maximum reply depth. The request is made by the visitor's browser, so the AppView also receives the visitor's IP address and browser details, as with any web request.
* **When:** every time a visitor opens a post that is linked to an AT Protocol post. Nothing is sent on other pages, and the WordPress server itself makes no requests.

Bluesky's [Terms of Service](https://bsky.social/about/support/tos) and [Privacy Policy](https://bsky.social/about/support/privacy-policy) apply to the default AppView. If you configure another AppView, its own terms apply.

The comments show avatars served by the AppView's image CDN (for Bluesky, `cdn.bsky.app`), which the visitor's browser loads directly.

== Privacy ==

When a visitor chooses the app they want to reply with, the choice is stored in their browser's `localStorage` under the key `atproto-preferred-app`, so it is remembered on their next visit. It is never sent to your site or to anyone else. The plugin sets no cookies.

Consider mentioning the AppView and this `localStorage` entry in your site's privacy policy.

== Installation ==

1. Install and activate the plugin from Plugins → Add New, or upload the plugin folder to `/wp-content/plugins/`.
2. Optionally, review Settings → AT Protocol Comments.
3. Publish a post and share it on Bluesky or another AT Protocol app.
4. Edit the post and paste the link to that Bluesky post in the "AT Protocol comments" panel (block editor) or box (classic editor). Save.

== Frequently Asked Questions ==

= Where do I get the link to the post? =

Open the post in bsky.app, mu.social or another app and copy its URL. It must contain the DID of the account, which starts with `did:`, like `https://bsky.app/profile/did:plc:abc…/post/3k…`. You can also paste the `at://` URI, like `at://did:plc:abc…/app.bsky.feed.post/3k…`.

URLs with a handle (like `https://bsky.app/profile/alice.bsky.social/post/3k…`) are not accepted yet. Replace the handle with the account's DID, which you can look up at `https://public.api.bsky.app/xrpc/com.atproto.identity.resolveHandle?handle=alice.bsky.social`.

= My theme doesn't show the comments =

In "Replace the native comments" mode, the plugin takes the place of the theme's comments area. Some themes, especially block themes with custom templates, don't have one in their single post template. Switch to "After the content" in Settings → AT Protocol Comments, which works with any theme.

= How do I turn off native WordPress comments? =

In "Replace" mode the native comment list and form are hidden on linked posts, but WordPress still accepts comments sent directly (often spam). If you don't want native comments, close them on each post (Discussion panel) or for new posts in Settings → Discussion. The AT Protocol comments are still shown when native comments are closed.

= Can visitors reply from my site? =

Not directly. The "Comment" and "Reply" buttons open the conversation in the visitor's AT Protocol app, where they reply with their own account.

= What happens if I uninstall the plugin? =

Its settings are deleted. The links stored in your posts are kept, so they are back if you install it again.

== Changelog ==

= 0.1.0 =
* First version: link posts to an AT Protocol post and show its replies as comments.
