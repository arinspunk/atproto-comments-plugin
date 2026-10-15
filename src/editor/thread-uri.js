/**
 * Thread link normalization and validation.
 *
 * Mirrors includes/class-thread-uri.php; both are tested against
 * tests/fixtures/thread-uris.json.
 */

const DID = 'did:plc:[a-z2-7]{24}|did:web:[a-zA-Z0-9.-]+(?:%3A[0-9]+)?';
const RKEY = '(?!\\.\\.?$)[A-Za-z0-9._~:-]{1,512}';
const HANDLE =
	'[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+';
const COLLECTION = 'app.bsky.feed.post';

const AT_URI = /^at:\/\/([^/]+)\/app\.bsky\.feed\.post\/([^/?#]*)$/;
const APP_URL =
	/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?\/profile\/([^/?#]+)\/post\/([^/?#]+)\/?(?:[?#][\s\S]*)?$/;
const DID_RE = new RegExp( `^(?:${ DID })$` );
const RKEY_RE = new RegExp( `^${ RKEY }$` );
const HANDLE_RE = new RegExp( `^${ HANDLE }$` );

// Same characters as PHP's trim(), so both implementations agree.
const TRIM_RE = /^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g;

/**
 * Normalizes an at:// URI or an app URL with a DID to the canonical at:// form.
 *
 * @param {string} value Raw input.
 * @return {{ok: boolean, uri?: string, error?: 'handle'|'format'}} On success
 *         `uri` is the canonical at:// URI ('' unlinks).
 */
export function normalize( value ) {
	const trimmed = String( value ?? '' ).replace( TRIM_RE, '' );
	if ( trimmed === '' ) {
		return { ok: true, uri: '' };
	}

	const m = AT_URI.exec( trimmed ) || APP_URL.exec( trimmed );
	if ( m ) {
		const [ , authority, rkey ] = m;
		if ( RKEY_RE.test( rkey ) ) {
			if ( DID_RE.test( authority ) ) {
				return {
					ok: true,
					uri: `at://${ authority }/${ COLLECTION }/${ rkey }`,
				};
			}
			if (
				! authority.startsWith( 'did:' ) &&
				HANDLE_RE.test( authority )
			) {
				return { ok: false, error: 'handle' };
			}
		}
	}

	return { ok: false, error: 'format' };
}
