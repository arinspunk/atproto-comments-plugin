/**
 * Block editor panel to link a post to its AT Protocol thread.
 */

// Classic JSX runtime: the automatic one depends on the `react-jsx-runtime`
// script, which WordPress only registers since 6.6.
/* @jsxRuntime classic */
/* @jsx createElement */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useDispatch, useSelect } from '@wordpress/data';
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import { Notice, Spinner, TextControl } from '@wordpress/components';
// WP 6.6+ exports the panel from @wordpress/editor; WP 6.5 only from
// @wordpress/edit-post, whose alias is deprecated since 6.6.
import { PluginDocumentSettingPanel as EditorPanel } from '@wordpress/editor';
import { PluginDocumentSettingPanel as LegacyPanel } from '@wordpress/edit-post';

import LinkedState from './linked-state';
import PublishForm from './publish-form';
import { normalize } from './thread-uri';

const PluginDocumentSettingPanel = EditorPanel || LegacyPanel;

const META_KEY = '_atproto_thread_uri';

function errorMessage( error ) {
	if ( error === 'handle_unresolved' ) {
		return __(
			'The handle in this link could not be resolved. Check it, or paste the post URL or the at:// URI with the DID (did:…).',
			'atproto-comments'
		);
	}
	return __(
		'This is not a valid AT Protocol post link. Paste the URL of a post, like https://bsky.app/profile/…/post/…, or its at:// URI.',
		'atproto-comments'
	);
}

/**
 * Panel: the linked state when the entry has a saved link, and otherwise the
 * field to paste a link and the form to publish the post.
 *
 * @return {Element} Panel.
 */
function ThreadPanel() {
	const saved = useSelect(
		( select ) =>
			select( 'core/editor' ).getCurrentPostAttribute( 'meta' )?.[
				META_KEY
			] ?? '',
		[]
	);
	// Success message of the last action, with the saved link it led to. It
	// is shown while that link does not change.
	const [ done, setDone ] = useState( null );
	const onDone = ( message, link ) => setDone( { message, link } );

	return (
		<PluginDocumentSettingPanel
			name="atproto-comments-thread"
			title={ __( 'AT Protocol comments', 'atproto-comments' ) }
		>
			{ done && done.link === saved && (
				<Notice status="success" onRemove={ () => setDone( null ) }>
					{ done.message }
				</Notice>
			) }
			{ saved ? (
				<LinkedState uri={ saved } onDone={ onDone } />
			) : (
				<LinkField onPublished={ onDone } />
			) }
		</PluginDocumentSettingPanel>
	);
}

/**
 * Field to paste the link, and the publish form.
 *
 * @param {Object}                                  props             Props.
 * @param {(message: string, link: string) => void} props.onPublished Called with a success message and the new link.
 * @return {Element} Field and form.
 */
function LinkField( { onPublished } ) {
	const stored = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )?.[
				META_KEY
			] ?? '',
		[]
	);
	const { editPost } = useDispatch( 'core/editor' );
	const [ value, setValue ] = useState( stored );
	const [ error, setError ] = useState( null );
	const [ resolving, setResolving ] = useState( false );
	// Identifies the latest commit, so a slow handle resolution does not
	// overwrite a newer value.
	const latest = useRef( 0 );

	// Follow changes made elsewhere (e.g. undo).
	useEffect( () => {
		setValue( stored );
		setError( null );
	}, [ stored ] );

	const save = ( uri ) => {
		setError( null );
		setValue( uri );
		if ( uri !== stored ) {
			editPost( { meta: { [ META_KEY ]: uri } } );
		}
	};

	const commit = async () => {
		const request = ++latest.current;
		const result = normalize( value );
		setResolving( false );

		if ( result.ok ) {
			save( result.uri );
			return;
		}
		if ( result.error !== 'handle' ) {
			setError( result.error );
			return;
		}

		// Handles are resolved to a DID on the server; the meta only
		// accepts the canonical at:// URI.
		setError( null );
		setResolving( true );
		let uri = null;
		try {
			const { did } = await apiFetch( {
				path: `/atproto-comments/v1/resolve-handle?handle=${ encodeURIComponent(
					result.handle
				) }`,
			} );
			const resolved = normalize(
				`at://${ did }/app.bsky.feed.post/${ result.rkey }`
			);
			uri = resolved.ok ? resolved.uri : null;
		} catch {
			uri = null;
		}
		if ( request !== latest.current ) {
			return;
		}
		setResolving( false );
		if ( uri ) {
			save( uri );
		} else {
			setError( 'handle_unresolved' );
		}
	};

	return (
		<div>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Thread link', 'atproto-comments' ) }
				help={ __(
					'Paste the URL of the post or its at:// URI. Leave empty to unlink.',
					'atproto-comments'
				) }
				placeholder="at://did:plc:…/app.bsky.feed.post/…"
				value={ value }
				onChange={ setValue }
				onBlur={ commit }
				onKeyDown={ ( event ) => {
					if ( event.key === 'Enter' ) {
						event.preventDefault();
						commit();
					}
				} }
			/>
			{ resolving && (
				<p aria-live="polite">
					<Spinner />
					{ __( 'Resolving the handle…', 'atproto-comments' ) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( error ) }
				</Notice>
			) }
			<PublishForm onPublished={ onPublished } />
		</div>
	);
}

registerPlugin( 'atproto-comments', { render: ThreadPanel } );
