/**
 * Panel state of a linked entry: the link, a link to the post, and the
 * actions to unlink it or delete the post.
 */

/* @jsxRuntime classic */
/* @jsx createElement */
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useRegistry, useSelect } from '@wordpress/data';
import { createElement, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	ExternalLink,
	Modal,
	Notice,
	Spinner,
} from '@wordpress/components';

import { deleteState, fetchReplyCount, postUrl } from './link-helpers';
import { persistLink } from './persist-link';

/**
 * Data passed by PHP.
 *
 * @return {Object} Editor data.
 */
function editorData() {
	return window.atprotoCommentsEditor || {};
}

/**
 * Message for a failed deletion. Errors of the plugin, and WordPress errors
 * with a 4xx status, say what happened. Anything else gives no answer from
 * the plugin, so it is not known whether the post was deleted; trying again
 * is safe.
 *
 * @param {Object} error Error thrown by apiFetch.
 * @return {string} Message.
 */
function deleteFailure( error ) {
	const code = typeof error?.code === 'string' ? error.code : '';
	const status = error?.data?.status;
	if (
		error?.message &&
		( code.startsWith( 'atproto_comments_' ) ||
			( typeof status === 'number' && status >= 400 && status < 500 ) )
	) {
		return error.message;
	}
	return __(
		'It could not be confirmed that the post was deleted. Try again.',
		'parolas'
	);
}

/**
 * Text of the confirmation about the replies.
 *
 * @param {number|null|undefined} count Replies; null if unknown, undefined
 *                                      while loading.
 * @return {string|null} Text, or null while loading.
 */
function repliesText( count ) {
	if ( count === undefined ) {
		return null;
	}
	if ( count === null ) {
		return __(
			'The number of replies could not be checked. If the post has replies, they will no longer be shown in its thread or on this entry, but they will stay on the profiles of the people who wrote them.',
			'parolas'
		);
	}
	if ( count === 0 ) {
		return __( 'The post has no replies.', 'parolas' );
	}
	return sprintf(
		/* translators: %d: approximate number of replies to the post. */
		_n(
			'About %d reply will no longer be shown in the thread or on this entry. It will stay on the profile of the person who wrote it.',
			'About %d replies will no longer be shown in the thread or on this entry. They will stay on the profiles of the people who wrote them.',
			count,
			'parolas'
		),
		count
	);
}

/**
 * Dialog to confirm the deletion.
 *
 * @param {Object}     props           Props.
 * @param {string}     props.uri       at:// URI of the post.
 * @param {string}     props.appview   AppView URL.
 * @param {() => void} props.onConfirm Called when confirmed.
 * @param {() => void} props.onCancel  Called when cancelled.
 * @return {Element} Dialog.
 */
function ConfirmDelete( { uri, appview, onConfirm, onCancel } ) {
	const [ count, setCount ] = useState( undefined );

	useEffect( () => {
		const controller = new AbortController();
		fetchReplyCount( appview, uri, { signal: controller.signal } ).then(
			( value ) => {
				if ( ! controller.signal.aborted ) {
					setCount( value );
				}
			}
		);
		return () => controller.abort();
	}, [ appview, uri ] );

	const text = repliesText( count );

	return (
		<Modal
			title={ __( 'Delete the post from Bluesky?', 'parolas' ) }
			onRequestClose={ onCancel }
			size="small"
		>
			<p>
				{ __(
					'The post will be deleted from the site’s AT Protocol account and the entry will be unlinked. This cannot be undone.',
					'parolas'
				) }
			</p>
			<p aria-live="polite">
				{ text ?? (
					<span>
						<Spinner />
						{ __( 'Checking the number of replies…', 'parolas' ) }
					</span>
				) }
			</p>
			<div
				className="atproto-comments-confirm-actions"
				style={ {
					display: 'flex',
					justifyContent: 'flex-end',
					gap: '8px',
				} }
			>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'parolas' ) }
				</Button>
				<Button variant="primary" isDestructive onClick={ onConfirm }>
					{ __( 'Delete the post', 'parolas' ) }
				</Button>
			</div>
		</Modal>
	);
}

/**
 * Linked state of the panel.
 *
 * @param {Object}                                  props        Props.
 * @param {string}                                  props.uri    Saved at:// URI.
 * @param {(message: string, link: string) => void} props.onDone Called with a success message and the new link ('') after unlinking or deleting.
 * @return {Element} Linked state.
 */
export default function LinkedState( { uri, onDone } ) {
	const data = editorData();
	const { postId, postType } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
		};
	}, [] );
	const registry = useRegistry();

	const [ busy, setBusy ] = useState( null );
	const [ confirming, setConfirming ] = useState( false );
	const [ error, setError ] = useState( null );
	// Whether the state is still shown when a request ends. Set in the effect
	// too: StrictMode runs effects twice, with a cleanup in between.
	const mounted = useRef( true );
	useEffect( () => {
		mounted.current = true;
		return () => {
			mounted.current = false;
		};
	}, [] );

	const url = postUrl( data.postUrlTemplate, uri );
	const state = deleteState( uri, data );

	const unlink = async () => {
		setBusy( 'unlink' );
		setError( null );
		try {
			const type = await registry
				.resolveSelect( 'core' )
				.getPostType( postType );
			const namespace = type?.rest_namespace || 'wp/v2';
			const base = type?.rest_base || postType;
			// null deletes the meta row; the editor keeps '' as saved value.
			await apiFetch( {
				path: `/${ namespace }/${ base }/${ postId }`,
				method: 'POST',
				data: { meta: { _atproto_thread_uri: null } },
			} );
			persistLink( registry, postType, postId, '' );
			onDone(
				__(
					'The entry is unlinked. The post and its replies are still on Bluesky.',
					'parolas'
				),
				''
			);
		} catch ( failure ) {
			if ( mounted.current ) {
				setError(
					failure?.message ||
						__(
							'The entry could not be unlinked. Try again.',
							'parolas'
						)
				);
			}
		} finally {
			if ( mounted.current ) {
				setBusy( null );
			}
		}
	};

	const remove = async () => {
		setConfirming( false );
		setBusy( 'delete' );
		setError( null );
		try {
			await apiFetch( {
				path: `/atproto-comments/v1/posts/${ postId }/anchor-post`,
				method: 'DELETE',
			} );
			persistLink( registry, postType, postId, '' );
			onDone(
				__(
					'The post was deleted from Bluesky and the entry is unlinked.',
					'parolas'
				),
				''
			);
		} catch ( failure ) {
			if ( mounted.current ) {
				setError( deleteFailure( failure ) );
			}
		} finally {
			if ( mounted.current ) {
				setBusy( null );
			}
		}
	};

	return (
		<div className="atproto-comments-linked">
			<p>{ __( 'This entry is linked to the post:', 'parolas' ) }</p>
			<p>
				<code
					className="atproto-comments-linked__uri"
					style={ { wordBreak: 'break-all' } }
				>
					{ uri }
				</code>
			</p>
			{ url && (
				<p>
					<ExternalLink href={ url }>
						{ __( 'Open the post', 'parolas' ) }
					</ExternalLink>
				</p>
			) }
			<div
				className="atproto-comments-linked__actions"
				style={ { display: 'flex', flexWrap: 'wrap', gap: '8px' } }
			>
				<Button
					variant="secondary"
					onClick={ unlink }
					isBusy={ busy === 'unlink' }
					disabled={ busy !== null }
				>
					{ __( 'Unlink', 'parolas' ) }
				</Button>
				{ state !== 'hidden' && (
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => setConfirming( true ) }
						isBusy={ busy === 'delete' }
						disabled={ busy !== null || state !== 'enabled' }
						aria-describedby={
							state === 'needs-test'
								? 'atproto-comments-delete-hint'
								: undefined
						}
					>
						{ __( 'Delete from Bluesky', 'parolas' ) }
					</Button>
				) }
			</div>
			<p className="description">
				{ __(
					'Unlinking does not delete the post or its replies.',
					'parolas'
				) }
			</p>
			{ state === 'needs-test' && (
				<p className="description" id="atproto-comments-delete-hint">
					{ __(
						'Test the connection in the settings to be able to delete from here.',
						'parolas'
					) }
					{ data.settingsUrl && (
						<span>
							{ ' ' }
							<a href={ data.settingsUrl }>
								{ __( 'Open the settings', 'parolas' ) }
							</a>
						</span>
					) }
				</p>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ confirming && (
				<ConfirmDelete
					uri={ uri }
					appview={ data.appview }
					onConfirm={ remove }
					onCancel={ () => setConfirming( false ) }
				/>
			) }
		</div>
	);
}
