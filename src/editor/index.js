/**
 * Block editor panel to link a post to its AT Protocol thread.
 */

// Classic JSX runtime: the automatic one depends on the `react-jsx-runtime`
// script, which WordPress only registers since 6.6.
/* @jsxRuntime classic */
/* @jsx createElement */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useDispatch, useSelect } from '@wordpress/data';
import { createElement, useEffect, useState } from '@wordpress/element';
import { Notice, TextControl } from '@wordpress/components';
// WP 6.6+ exports the panel from @wordpress/editor; WP 6.5 only from
// @wordpress/edit-post, whose alias is deprecated since 6.6.
import { PluginDocumentSettingPanel as EditorPanel } from '@wordpress/editor';
import { PluginDocumentSettingPanel as LegacyPanel } from '@wordpress/edit-post';

import { normalize } from './thread-uri';

const PluginDocumentSettingPanel = EditorPanel || LegacyPanel;

const META_KEY = '_atproto_thread_uri';

function errorMessage( error ) {
	if ( error === 'handle' ) {
		return __(
			'This link uses a handle. Paste the at:// URI or the post URL with the DID (did:…) instead.',
			'atproto-comments'
		);
	}
	return __(
		'This is not a valid AT Protocol post link. Paste an at:// URI of a post or a post URL with the DID, like https://bsky.app/profile/did:plc:…/post/….',
		'atproto-comments'
	);
}

function ThreadPanel() {
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

	// Follow changes made elsewhere (e.g. undo).
	useEffect( () => {
		setValue( stored );
		setError( null );
	}, [ stored ] );

	const commit = () => {
		const result = normalize( value );
		if ( ! result.ok ) {
			setError( result.error );
			return;
		}
		setError( null );
		setValue( result.uri );
		if ( result.uri !== stored ) {
			editPost( { meta: { [ META_KEY ]: result.uri } } );
		}
	};

	return (
		<PluginDocumentSettingPanel
			name="atproto-comments-thread"
			title={ __( 'AT Protocol comments', 'atproto-comments' ) }
		>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Thread link', 'atproto-comments' ) }
				help={ __(
					'Paste the at:// URI of the post, or its URL with the DID (did:…). Leave empty to unlink.',
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
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( error ) }
				</Notice>
			) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'atproto-comments', { render: ThreadPanel } );
