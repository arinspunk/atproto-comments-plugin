/**
 * "Test connection" button of the settings page.
 */

import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __, sprintf } from '@wordpress/i18n';

const FIELD_PREFIX = 'atproto_comments_account';

function show( region, type, lines ) {
	const notice = document.createElement( 'div' );
	notice.className = `notice notice-${ type } inline`;
	for ( const line of lines ) {
		const p = document.createElement( 'p' );
		p.textContent = line;
		notice.appendChild( p );
	}
	region.replaceChildren( notice );
}

function hasUnsavedAccount( form ) {
	if ( ! form ) {
		return false;
	}
	const identifier = form.elements[ `${ FIELD_PREFIX }[identifier]` ];
	const password = form.elements[ `${ FIELD_PREFIX }[app_password]` ];
	return (
		( identifier && identifier.value !== identifier.defaultValue ) ||
		( password && password.value !== '' )
	);
}

domReady( () => {
	const button = document.getElementById( 'atproto-comments-account-test' );
	const region = document.getElementById(
		'atproto-comments-account-test-result'
	);
	if ( ! button || ! region ) {
		return;
	}

	button.addEventListener( 'click', async () => {
		const unsaved = hasUnsavedAccount( button.form );

		button.disabled = true;
		button.setAttribute( 'aria-busy', 'true' );
		show( region, 'info', [ __( 'Testing the connection…', 'parolas' ) ] );

		const notes = unsaved
			? [
					__(
						'The form has unsaved account changes: the test used the saved account.',
						'parolas'
					),
				]
			: [];

		try {
			const result = await apiFetch( {
				path: '/atproto-comments/v1/account/test',
				method: 'POST',
			} );
			show( region, 'success', [
				__( 'Connection successful.', 'parolas' ),
				sprintf(
					/* translators: %s: account handle. */
					__( 'Handle: %s', 'parolas' ),
					result.handle
				),
				/* translators: %s: account DID. */
				sprintf( __( 'DID: %s', 'parolas' ), result.did ),
				/* translators: %s: URL of the account's server (PDS). */
				sprintf( __( 'Server: %s', 'parolas' ), result.pds ),
				...notes,
			] );
		} catch ( error ) {
			const message =
				error && error.message && error.code
					? error.message
					: __(
							'The connection could not be tested. Try again.',
							'parolas'
						);
			show( region, 'error', [ message, ...notes ] );
		} finally {
			button.disabled = false;
			button.removeAttribute( 'aria-busy' );
		}
	} );
} );
