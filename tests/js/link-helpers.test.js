/**
 * Helpers of the linked state of the panel.
 */
import { describe, expect, it, vi } from 'vitest';
import {
	deleteState,
	fetchReplyCount,
	postUrl,
	uriParts,
} from '../../src/editor/link-helpers';

const DID = 'did:plc:abc234567abcdefghijklmno';
const OTHER = 'did:plc:zzz234567abcdefghijklmno';
const URI = `at://${ DID }/app.bsky.feed.post/3mwso7oovnp2u`;
const TEMPLATE = 'https://bsky.app/profile/{did}/post/{rkey}';

describe( 'uriParts', () => {
	it( 'splits a canonical URI', () => {
		expect( uriParts( URI ) ).toEqual( {
			did: DID,
			rkey: '3mwso7oovnp2u',
		} );
	} );

	it( 'rejects other values', () => {
		expect( uriParts( '' ) ).toBeNull();
		expect(
			uriParts( `at://${ DID }/app.bsky.feed.like/3mwso7oovnp2u` )
		).toBeNull();
		expect( uriParts( undefined ) ).toBeNull();
	} );
} );

describe( 'postUrl', () => {
	it( 'fills the default template', () => {
		expect( postUrl( TEMPLATE, URI ) ).toBe(
			`https://bsky.app/profile/${ DID }/post/3mwso7oovnp2u`
		);
	} );

	it( 'fills another template, with repeated placeholders', () => {
		expect(
			postUrl( 'https://app.example/{did}/{rkey}?d={did}', URI )
		).toBe( `https://app.example/${ DID }/3mwso7oovnp2u?d=${ DID }` );
	} );

	it( 'keeps the %3A of a did:web with port', () => {
		expect(
			postUrl(
				TEMPLATE,
				'at://did:web:example.com%3A8443/app.bsky.feed.post/3abc'
			)
		).toBe(
			'https://bsky.app/profile/did:web:example.com%3A8443/post/3abc'
		);
	} );

	it( 'returns null without a template or a valid URI', () => {
		expect( postUrl( '', URI ) ).toBeNull();
		expect( postUrl( TEMPLATE, 'nope' ) ).toBeNull();
	} );
} );

describe( 'deleteState', () => {
	const ready = { canDelete: true, accountReady: true, accountDid: DID };

	it( 'is enabled for a post of the account', () => {
		expect( deleteState( URI, ready ) ).toBe( 'enabled' );
	} );

	it( 'is hidden for a post of another account', () => {
		expect( deleteState( URI, { ...ready, accountDid: OTHER } ) ).toBe(
			'hidden'
		);
	} );

	it( 'needs a connection test when the account DID is unknown', () => {
		expect( deleteState( URI, { ...ready, accountDid: '' } ) ).toBe(
			'needs-test'
		);
	} );

	it( 'is hidden without permission', () => {
		expect( deleteState( URI, { ...ready, canDelete: false } ) ).toBe(
			'hidden'
		);
		expect(
			deleteState( URI, {
				...ready,
				canDelete: false,
				accountDid: '',
			} )
		).toBe( 'hidden' );
	} );

	it( 'is hidden without an account', () => {
		expect(
			deleteState( URI, {
				...ready,
				accountReady: false,
				accountDid: '',
			} )
		).toBe( 'hidden' );
		expect( deleteState( URI, { ...ready, accountReady: false } ) ).toBe(
			'hidden'
		);
	} );

	it( 'is hidden without data or with an invalid URI', () => {
		expect( deleteState( URI, undefined ) ).toBe( 'hidden' );
		expect( deleteState( 'nope', ready ) ).toBe( 'hidden' );
	} );
} );

describe( 'fetchReplyCount', () => {
	const json = ( body, ok = true ) =>
		vi.fn( async () => ( { ok, json: async () => body } ) );

	it( 'reads replyCount from getPosts', async () => {
		const fetchImpl = json( { posts: [ { replyCount: 12 } ] } );

		expect(
			await fetchReplyCount( 'https://appview.example/', URI, {
				fetchImpl,
			} )
		).toBe( 12 );
		expect( fetchImpl.mock.calls[ 0 ][ 0 ] ).toBe(
			`https://appview.example/xrpc/app.bsky.feed.getPosts?uris=${ encodeURIComponent(
				URI
			) }`
		);
		expect( fetchImpl.mock.calls[ 0 ][ 1 ].credentials ).toBe( 'omit' );
	} );

	it( 'returns null on errors and unexpected bodies', async () => {
		expect(
			await fetchReplyCount( 'https://a.example', URI, {
				fetchImpl: json( {}, false ),
			} )
		).toBeNull();
		expect(
			await fetchReplyCount( 'https://a.example', URI, {
				fetchImpl: json( { posts: [] } ),
			} )
		).toBeNull();
		expect(
			await fetchReplyCount( 'https://a.example', URI, {
				fetchImpl: json( { posts: [ { replyCount: -1 } ] } ),
			} )
		).toBeNull();
		expect(
			await fetchReplyCount( 'https://a.example', URI, {
				fetchImpl: vi.fn( async () => {
					throw new TypeError( 'Failed to fetch' );
				} ),
			} )
		).toBeNull();
	} );

	it( 'gives up after the timeout', async () => {
		const fetchImpl = vi.fn(
			( url, { signal } ) =>
				new Promise( ( resolve, reject ) => {
					signal.addEventListener( 'abort', () =>
						reject( new Error( 'aborted' ) )
					);
				} )
		);

		expect(
			await fetchReplyCount( 'https://a.example', URI, {
				fetchImpl,
				timeout: 10,
			} )
		).toBeNull();
	} );

	it( 'does not call the AppView without one or with an invalid URI', async () => {
		const fetchImpl = json( { posts: [ { replyCount: 1 } ] } );

		expect( await fetchReplyCount( '', URI, { fetchImpl } ) ).toBeNull();
		expect(
			await fetchReplyCount( 'https://a.example', 'nope', { fetchImpl } )
		).toBeNull();
		expect( fetchImpl ).not.toHaveBeenCalled();
	} );
} );
