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

Publish your post and then publish its Bluesky post from the editor, with your own text and a card that links back to the entry. Or share it yourself and paste the link to that post. The replies appear under your post, threaded, with their authors and dates. Visitors reply from their own app; there is nothing to moderate in WordPress and no accounts to create.

* Publish the anchor post from the block editor, when you decide, in the site's AT Protocol account (Bluesky or any other server).
* Or paste an `at://` URI or the post URL from bsky.app, mu.social or any compatible app, with a handle or a DID.
* Unlink an entry at any time, or delete its anchor post from Bluesky when it belongs to the site account.
* Works with block and classic themes.
* Replace the native comments, or show both, one after the other.
* Choose the content types where it is active (posts by default).
* Interface and dates in your site's language.
* Scripts and styles load only on the posts that show AT Protocol comments.

The comments are displayed by the [atproto-comments](https://github.com/arinspunk/atproto-comments) web component, bundled with the plugin. Its source code is available on [GitHub](https://github.com/arinspunk/atproto-comments) and as the [atproto-comments npm package](https://www.npmjs.com/package/atproto-comments). The source of this plugin, including its block editor panel, is on [GitHub](https://github.com/arinspunk/atproto-comments-plugin).

== External services ==

This plugin connects to AT Protocol services. The visitor's browser loads the replies; the WordPress server only makes requests when an editor publishes or deletes an anchor post, tests the account connection or saves a link with a handle.

= AppView (visitor's browser) =

The replies to the linked post are loaded from an AT Protocol AppView. By default it is the public AppView run by Bluesky Social PBC, `https://public.api.bsky.app`. You can change it in Settings → AT Protocol Comments.

* **What is sent:** the `at://` URI of the linked post and the maximum reply depth. The request is made by the visitor's browser, so the AppView also receives the visitor's IP address and browser details, as with any web request.
* **When:** every time a visitor opens a post that is linked to an AT Protocol post. Nothing is sent on other pages.

The comments show avatars served by the AppView's image CDN (for Bluesky, `cdn.bsky.app`), which the visitor's browser loads directly.

The editor's browser also asks the same AppView for the number of replies of the linked post when an editor clicks "Delete from Bluesky", to show it in the confirmation. It sends the `at://` URI of the post, along with the editor's IP address and browser details.

= The account's server (PDS) =

If you configure an AT Protocol account in the settings, the WordPress server connects to that account's Personal Data Server (PDS). The plugin does not hard-code any server: the PDS is the one the account's DID document declares, which can be one of Bluesky's servers or another provider's, such as Eurosky.

* **What is sent:** to sign in, the account's DID and its app password. To publish, the post text you write, the creation date, the site language (for example `es`), and a card with the entry's permalink, title and excerpt (no excerpt for password-protected entries). If the entry has a featured image of 1 MB or less, the image file too. To delete, the post's repository (the account's DID) and record key; if the server's answer is not clear, a public read of that same record confirms whether it is gone.
* **When:** only when an editor clicks "Publish to Bluesky" or "Delete from Bluesky", or an administrator clicks "Test connection". Publishing, updating, trashing or deleting an entry never contacts the PDS.

= Identity resolution =

To find an account's DID and PDS, the WordPress server uses the standard AT Protocol methods:

* **The handle's domain:** a DNS lookup of `_atproto.<handle>` and a request to `https://<handle>/.well-known/atproto-did`. Only the handle is sent.
* **The DID directory:** for `did:plc` accounts, `https://plc.directory/<did>`, run by Bluesky Social PBC; for `did:web` accounts, `https://<domain>/.well-known/did.json`. Only the DID is sent.
* **When:** when publishing or deleting an anchor post, testing the connection, or saving a thread link that uses a handle instead of a DID (the result is cached for 10 minutes).

Bluesky's [Terms of Service](https://bsky.social/about/support/tos) and [Privacy Policy](https://bsky.social/about/support/privacy-policy) apply to the default AppView, to `plc.directory` and to accounts hosted on Bluesky's servers. If you use another AppView or an account on another provider, its own terms apply.

== Privacy ==

When a visitor chooses the app they want to reply with, the choice is stored in their browser's `localStorage` under the key `atproto-preferred-app`, so it is remembered on their next visit. It is never sent to your site or to anyone else. The plugin sets no cookies.

The app password of the site's AT Protocol account is stored in the WordPress database, encrypted with a key derived from your site's security keys (`AUTH_KEY` and `AUTH_SALT`). It is never shown in the settings or sent to the browser, and it is deleted when you choose "Forget account" or uninstall the plugin. Use an app password, never the account's main password.

Consider mentioning the AppView, the publishing of anchor posts and this `localStorage` entry in your site's privacy policy.

== Installation ==

1. Install and activate the plugin from Plugins → Add New, or upload the plugin folder to `/wp-content/plugins/`.
2. Optionally, review Settings → AT Protocol Comments.
3. To publish from the editor, add the site's AT Protocol account in Settings → AT Protocol Comments: its handle and an app password. Click "Test connection" to check it.
4. Publish an entry. In the "AT Protocol comments" panel of the block editor, write the text of the post and click "Publish to Bluesky".
5. Or share the entry yourself in any AT Protocol app, then paste the link to that post in the "AT Protocol comments" panel (block editor) or box (classic editor), and save.

== Frequently Asked Questions ==

= How do I create an app password? =

An app password lets an app use your account without your main password, and you can revoke it at any time. In Bluesky, go to Settings → Privacy and security → App passwords, add one (for example "WordPress") and copy it into Settings → AT Protocol Comments. Other providers have a similar option. Don't give it access to direct messages: the plugin doesn't need it.

= How do I publish the anchor post? =

Publish the entry first and save any pending changes. Then, in the "AT Protocol comments" panel of the block editor, write the text of the post (up to 300 characters) and click "Publish to Bluesky". The post includes a card with the entry's link, title, excerpt and featured image, and the entry is linked to it automatically. Publishing or updating the entry never creates a post by itself. The classic editor can't publish; paste the link instead.

= Who can publish? =

Only users who can edit other people's posts, that is, Editors and Administrators, because the post goes out in the site's account, not the author's. Authors and Contributors can still paste a link. To change it, return another capability from the `atproto_comments_publish_capability` filter, for example in a small plugin:

`add_filter( 'atproto_comments_publish_capability', function () { return 'publish_posts'; } );`

With `publish_posts`, Authors can publish from their own entries; with `manage_options`, only Administrators can. The user also needs to be able to edit the entry.

= Where do I get the link to the post? =

Open the post in bsky.app, mu.social or another app and copy its URL, like `https://bsky.app/profile/alice.bsky.social/post/3k…`. You can also paste the `at://` URI, like `at://did:plc:abc…/app.bsky.feed.post/3k…`. Links with a handle are converted to the account's DID when you save. If the handle can't be resolved, paste the URL or the `at://` URI with the DID (`did:…`) instead.

= The card has no image =

The plugin uses the `medium_large` size of the featured image, or the original if there is no such size, and only if the file is 1 MB or less. It reads the file from your server, so there is no image if your media is stored elsewhere (for example, offloaded to S3 or a CDN). If the server is slow and there isn't enough time left, the post is published without image rather than failing.

= How do I unlink an entry or delete its post? =

When an entry is linked, the "AT Protocol comments" panel of the block editor shows the post and two actions:

* **Unlink** removes the link from the entry, right away. The post and its replies stay on Bluesky; you can then paste another link or publish a new post. Anyone who can edit the entry can unlink it.
* **Delete from Bluesky** deletes the post from the site account and then unlinks the entry. It asks for confirmation first and shows about how many replies the post has. It is only offered when the post belongs to the site account, to the same users who can publish (see "Who can publish?") and who can also delete the entry.

In the classic editor, empty the thread link field and update the entry to unlink it; deleting the post is only available in the block editor.

= What happens to the replies when I delete the post? =

Replies are records in their authors' accounts, so they are not deleted. They are no longer shown in the post's thread or on your entry, but they stay on the profiles of the people who wrote them.

= Does deleting or trashing the entry delete its post? =

No. Trashing, restoring or permanently deleting an entry in WordPress never touches the post on Bluesky. Delete it from the panel first if you want it gone.

= "Delete from Bluesky" is disabled =

The site doesn't know the account's DID yet. It learns it the first time the account is resolved: when you test the connection in Settings → AT Protocol Comments or publish a post. Test the connection once and the button is enabled. This can happen after updating the plugin or changing the account's handle.

= It says it could not be confirmed that the post was deleted =

The account's server didn't answer in time, or the request to your site timed out. The entry stays linked. Trying again is safe: if the post was already deleted, the entry is simply unlinked.

= It says it is not known whether the post was created =

The connection with the account's server failed after the post was sent, or the request to your site timed out. The post may or may not exist. Open the account's profile in Bluesky or another app: if the post is there, copy its link and paste it in the thread link field; if it isn't, try again. Retrying without checking may create a duplicate.

= My account's server is on a private network =

For security, the WordPress server only connects to public addresses, so it can't reach a PDS, a handle domain or a `did:web` domain on a private or local network. A developer can allow a host with the WordPress `http_request_host_is_external` filter.

= My theme doesn't show the comments =

In "Replace the native comments" mode, the plugin takes the place of the theme's comments area. Some themes, especially block themes with custom templates, don't have one in their single post template. Switch to "After the content" in Settings → AT Protocol Comments, which works with any theme.

= How do I turn off native WordPress comments? =

In "Replace" mode the native comment list and form are hidden on linked posts, but WordPress still accepts comments sent directly (often spam). If you don't want native comments, close them on each post (Discussion panel) or for new posts in Settings → Discussion. The AT Protocol comments are still shown when native comments are closed.

= Can visitors reply from my site? =

Not directly. The "Comment" and "Reply" buttons open the conversation in the visitor's AT Protocol app, where they reply with their own account.

= What happens if I uninstall the plugin? =

Its settings and the AT Protocol account (handle and encrypted app password) are deleted. The links stored in your posts are kept, so they are back if you install it again. Posts published on AT Protocol are not deleted.

== Changelog ==

= Unreleased =
* Publish the anchor post from the block editor, with a card linking to the entry and the site language.
* Site AT Protocol account with an encrypted app password and a connection test.
* Accounts on any AT Protocol server: the handle, DID and PDS are resolved, nothing is hard-coded.
* Links with a handle are accepted and converted to the DID.
* Only Editors and Administrators can publish, adjustable with the `atproto_comments_publish_capability` filter.
* Unlink an entry from the block editor with one click, without touching the post.
* Delete the anchor post from Bluesky when it belongs to the site account, with a confirmation that shows the number of replies.
* The link to open the linked post can point to another app with the `atproto_comments_post_url` filter.

= 0.1.0 =
* First version: link posts to an AT Protocol post and show its replies as comments.
