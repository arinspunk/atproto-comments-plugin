/**
 * Pure helpers of the linked state of the panel.
 */

const URI = /^at:\/\/([^/]+)\/app\.bsky\.feed\.post\/([^/]+)$/;

/**
 * DID and rkey of a canonical at:// URI.
 *
 * @param {string} uri at:// URI.
 * @return {{did: string, rkey: string}|null} Parts, or null.
 */
export function uriParts( uri ) {
	const match = URI.exec( typeof uri === 'string' ? uri : '' );
	return match ? { did: match[ 1 ], rkey: match[ 2 ] } : null;
}

/**
 * URL to open the post in an app.
 *
 * @param {string} template https URL with `{did}` and `{rkey}`.
 * @param {string} uri      at:// URI.
 * @return {string|null} URL, or null.
 */
export function postUrl( template, uri ) {
	const parts = uriParts( uri );
	if ( ! parts || typeof template !== 'string' || template === '' ) {
		return null;
	}
	return template
		.split( '{did}' )
		.join( parts.did )
		.split( '{rkey}' )
		.join( parts.rkey );
}

/**
 * State of the "Delete from Bluesky" button.
 *
 * - `enabled`: the user can delete, the account is ready and the post is
 *   its own.
 * - `needs-test`: the user can delete and the account is ready, but its DID
 *   is not known yet (it has never been resolved).
 * - `hidden`: anything else.
 *
 * @param {string} uri  Saved at:// URI.
 * @param {Object} data Editor data: canDelete, accountReady, accountDid.
 * @return {'enabled'|'needs-test'|'hidden'} State.
 */
export function deleteState( uri, data ) {
	const parts = uriParts( uri );
	if ( ! parts || ! data?.canDelete || ! data?.accountReady ) {
		return 'hidden';
	}
	if ( ! data.accountDid ) {
		return 'needs-test';
	}
	return parts.did === data.accountDid ? 'enabled' : 'hidden';
}

/**
 * Number of replies of a post, from the AppView. Null if it cannot be known
 * in time.
 *
 * @param {string}       appview           AppView URL.
 * @param {string}       uri               at:// URI.
 * @param {Object}       options           Options.
 * @param {number}       options.timeout   Milliseconds. Default 5000.
 * @param {AbortSignal}  options.signal    Signal to cancel, if any.
 * @param {typeof fetch} options.fetchImpl fetch, replaceable in tests.
 * @return {Promise<number|null>} Number of replies.
 */
export async function fetchReplyCount( appview, uri, options = {} ) {
	if ( typeof appview !== 'string' || appview === '' || ! uriParts( uri ) ) {
		return null;
	}
	const fetchImpl = options.fetchImpl || globalThis.fetch;
	const controller = new AbortController();
	const timer = setTimeout(
		() => controller.abort(),
		options.timeout ?? 5000
	);
	options.signal?.addEventListener( 'abort', () => controller.abort() );
	const url = `${ appview.replace(
		/\/+$/,
		''
	) }/xrpc/app.bsky.feed.getPosts?uris=${ encodeURIComponent( uri ) }`;
	const count = await requestReplyCount(
		fetchImpl,
		url,
		controller.signal
	).catch( () => null );
	clearTimeout( timer );
	return count;
}

/**
 * Asks getPosts for the replyCount of the first post.
 *
 * @param {typeof fetch} fetchImpl fetch.
 * @param {string}       url       getPosts URL.
 * @param {AbortSignal}  signal    Signal.
 * @return {Promise<number|null>} Number of replies.
 */
async function requestReplyCount( fetchImpl, url, signal ) {
	const response = await fetchImpl( url, { signal, credentials: 'omit' } );
	if ( ! response.ok ) {
		return null;
	}
	const count = ( await response.json() )?.posts?.[ 0 ]?.replyCount;
	return Number.isInteger( count ) && count >= 0 ? count : null;
}
