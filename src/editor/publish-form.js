/**
 * Form to publish the anchor post of the entry from the panel.
 */

/* @jsxRuntime classic */
/* @jsx createElement */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { createElement, useState } from '@wordpress/element';
import { Button, Notice, TextareaControl } from '@wordpress/components';

import { countGraphemes } from './graphemes';

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
		'atproto-comments'
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

export default function PublishForm() {
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
	const { editPost } = useDispatch( 'core/editor' );
	const { receiveEntityRecords } = useDispatch( 'core' );

	const [ text, setText ] = useState( '' );
	const [ publishing, setPublishing ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	if ( ! canPublish ) {
		return (
			<p className="atproto-comments-publish">
				{ __(
					'Only editors can publish posts to the site’s AT Protocol account.',
					'atproto-comments'
				) }
			</p>
		);
	}

	if ( savedLink || editedLink ) {
		if ( notice?.status === 'success' ) {
			return (
				<Notice status="success" isDismissible={ false }>
					{ notice.message }
				</Notice>
			);
		}
		return (
			<p className="atproto-comments-publish">
				{ __(
					'This entry is already linked to a post. To publish a new one, empty the link and update the entry.',
					'atproto-comments'
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
			'atproto-comments'
		);
	} else if ( dirty || saving ) {
		reason = __(
			'The entry has unsaved changes. Update it first.',
			'atproto-comments'
		);
	} else if ( ! accountReady ) {
		reason = settingsUrl ? (
			<span>
				{ __(
					'The site has no AT Protocol account configured.',
					'atproto-comments'
				) }{ ' ' }
				<a href={ settingsUrl }>
					{ __(
						'Configure it in the settings.',
						'atproto-comments'
					) }
				</a>
			</span>
		) : (
			__(
				'The site has no AT Protocol account configured. Ask an administrator to set it up.',
				'atproto-comments'
			)
		);
	} else if ( text.trim() === '' ) {
		reason = __( 'Write the text of the post.', 'atproto-comments' );
	} else if ( tooLong ) {
		reason = sprintf(
			/* translators: %d: maximum number of characters, 300. */
			__(
				'The text is too long. The limit is %d characters.',
				'atproto-comments'
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

			// The server saved the link. Put it in the editor without marking
			// the entry as modified, as core-data does after a save: edit
			// the meta, then receive the record with that edit as persisted.
			editPost( { meta: { [ META_KEY ]: uri } } );
			const core = registry.select( 'core' );
			const edits = core.getEntityRecordEdits(
				'postType',
				postType,
				postId
			);
			const record = core.getEntityRecord( 'postType', postType, postId );
			if ( record ) {
				receiveEntityRecords(
					'postType',
					postType,
					[
						{
							...record,
							meta: { ...record.meta, [ META_KEY ]: uri },
						},
					],
					undefined,
					false,
					{ meta: edits?.meta }
				);
			}

			setText( '' );
			setNotice( {
				status: 'success',
				message: __(
					'The post was published and the entry is linked to it.',
					'atproto-comments'
				),
			} );
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
				label={ __( 'Post text', 'atproto-comments' ) }
				value={ text }
				onChange={ setText }
				disabled={ publishing }
				rows={ 4 }
				help={ sprintf(
					/* translators: 1: characters written, 2: limit, 300. */
					__( '%1$d / %2$d characters', 'atproto-comments' ),
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
				{ __( 'Publish to Bluesky', 'atproto-comments' ) }
			</Button>
			<p
				id="atproto-comments-publish-help"
				className="components-base-control__help"
			>
				{ __(
					'Creates the post with a card that links to this entry. It can be seen in any AT Protocol app, such as Bluesky.',
					'atproto-comments'
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
