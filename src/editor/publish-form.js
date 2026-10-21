/**
 * Form to publish the anchor post of the entry from the panel.
 */

/* @jsxRuntime classic */
/* @jsx createElement */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useRegistry, useSelect } from '@wordpress/data';
import { createElement, useState } from '@wordpress/element';
import { Button, Notice, TextareaControl } from '@wordpress/components';

import { countGraphemes } from './graphemes';
import { persistLink } from './persist-link';

const META_KEY = '_atproto_thread_uri';

const MAX_GRAPHEMES = 300;

/**
 * Data passed by PHP: { accountReady, canPublish, settingsUrl }.
 *
 * @return {Object} Editor data.
 */
function editorData() {
	return window.atprotoCommentsEditor || {};
}

/**
 * Message shown when it is not known whether the post was created.
 *
 * @return {string} Message.
 */
function uncertainMessage() {
	return __(
		'It is not known whether the post was created. Check the account’s profile before trying again; if the post is there, paste its link in the thread link field.',
		'parolas'
	);
}

/**
 * Message for a failed call. Errors of the plugin, and WordPress errors with
 * a 4xx status, say what happened. Anything else (500, 502 or 504 from a
 * proxy, a network failure, a PHP timeout) gives no answer from the plugin,
 * so the post may or may not exist.
 *
 * @param {Object} error Error thrown by apiFetch.
 * @return {{uncertain: boolean, message: string}} Notice.
 */
function failure( error ) {
	const code = typeof error?.code === 'string' ? error.code : '';
	const status = error?.data?.status;
	if ( code === 'atproto_comments_uncertain' ) {
		return { uncertain: true, message: uncertainMessage() };
	}
	if (
		error?.message &&
		( code.startsWith( 'atproto_comments_' ) ||
			( typeof status === 'number' && status >= 400 && status < 500 ) )
	) {
		return { uncertain: false, message: error.message };
	}
	return { uncertain: true, message: uncertainMessage() };
}

/**
 * Publish form.
 *
 * @param {Object}                                  props             Props.
 * @param {(message: string, link: string) => void} props.onPublished Called with a success message and the new link once the entry is linked.
 * @return {Element} Form.
 */
export default function PublishForm( { onPublished } ) {
	const { accountReady, canPublish, settingsUrl } = editorData();

	const { postId, postType, status, dirty, saving, savedLink, editedLink } =
		useSelect( ( select ) => {
			const editor = select( 'core/editor' );
			return {
				postId: editor.getCurrentPostId(),
				postType: editor.getCurrentPostType(),
				status: editor.getCurrentPostAttribute( 'status' ),
				dirty: editor.isEditedPostDirty(),
				saving: editor.isSavingPost(),
				savedLink:
					editor.getCurrentPostAttribute( 'meta' )?.[ META_KEY ] ??
					'',
				editedLink:
					editor.getEditedPostAttribute( 'meta' )?.[ META_KEY ] ?? '',
			};
		}, [] );
	const registry = useRegistry();

	const [ text, setText ] = useState( '' );
	const [ publishing, setPublishing ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	if ( ! canPublish ) {
		return (
			<p className="atproto-comments-publish">
				{ __(
					'Only editors can publish posts to the site’s AT Protocol account.',
					'parolas'
				) }
			</p>
		);
	}

	if ( savedLink || editedLink ) {
		return (
			<p className="atproto-comments-publish">
				{ __(
					'This entry is already linked to a post. To publish a new one, empty the link and update the entry.',
					'parolas'
				) }
			</p>
		);
	}

	const graphemes = countGraphemes( text );
	const tooLong = graphemes > MAX_GRAPHEMES;

	let reason = null;
	if ( status !== 'publish' ) {
		reason = __(
			'Publish the entry before publishing its post.',
			'parolas'
		);
	} else if ( dirty || saving ) {
		reason = __(
			'The entry has unsaved changes. Update it first.',
			'parolas'
		);
	} else if ( ! accountReady ) {
		reason = settingsUrl ? (
			<span>
				{ __(
					'The site has no AT Protocol account configured.',
					'parolas'
				) }{ ' ' }
				<a href={ settingsUrl }>
					{ __( 'Configure it in the settings.', 'parolas' ) }
				</a>
			</span>
		) : (
			__(
				'The site has no AT Protocol account configured. Ask an administrator to set it up.',
				'parolas'
			)
		);
	} else if ( text.trim() === '' ) {
		reason = __( 'Write the text of the post.', 'parolas' );
	} else if ( tooLong ) {
		reason = sprintf(
			/* translators: %d: maximum number of characters, 300. */
			__(
				'The text is too long. The limit is %d characters.',
				'parolas'
			),
			MAX_GRAPHEMES
		);
	}

	const publish = async () => {
		setPublishing( true );
		setNotice( null );
		try {
			const { uri } = await apiFetch( {
				path: `/atproto-comments/v1/posts/${ postId }/anchor-post`,
				method: 'POST',
				data: { text },
			} );

			// The server saved the link: mark it as saved in the editor. The
			// panel then shows the linked state instead of this form.
			persistLink( registry, postType, postId, uri );
			setText( '' );
			onPublished(
				__(
					'The post was published and the entry is linked to it.',
					'parolas'
				),
				uri
			);
		} catch ( error ) {
			const { uncertain, message } = failure( error );
			setNotice( { status: uncertain ? 'warning' : 'error', message } );
		} finally {
			setPublishing( false );
		}
	};

	return (
		<div className="atproto-comments-publish">
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Post text', 'parolas' ) }
				value={ text }
				onChange={ setText }
				disabled={ publishing }
				rows={ 4 }
				help={ sprintf(
					/* translators: 1: characters written, 2: limit, 300. */
					__( '%1$d / %2$d characters', 'parolas' ),
					graphemes,
					MAX_GRAPHEMES
				) }
			/>
			<Button
				variant="primary"
				onClick={ publish }
				disabled={ publishing || reason !== null }
				isBusy={ publishing }
				aria-describedby="atproto-comments-publish-help"
			>
				{ __( 'Publish to Bluesky', 'parolas' ) }
			</Button>
			<p
				id="atproto-comments-publish-help"
				className="components-base-control__help"
			>
				{ __(
					'Creates the post with a card that links to this entry. It can be seen in any AT Protocol app, such as Bluesky.',
					'parolas'
				) }
			</p>
			{ reason && (
				<p className="atproto-comments-publish__reason">{ reason }</p>
			) }
			{ notice && notice.status !== 'success' && (
				<Notice status={ notice.status } isDismissible={ false }>
					{ notice.message }
				</Notice>
			) }
		</div>
	);
}
