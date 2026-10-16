/**
 * Puts a link that the server already saved into the editor as saved.
 */

const META_KEY = '_atproto_thread_uri';

/**
 * Marks the link as saved without marking the entry as modified, as
 * core-data does after a save: edit the meta outside the undo history, then
 * receive the record with that edit as persisted. Without `undoIgnore`, an
 * undo would bring back the previous link as an unsaved change.
 *
 * @param {Object} registry Data registry.
 * @param {string} postType Post type.
 * @param {number} postId   Post ID.
 * @param {string} value    Saved link, or '' when unlinked.
 */
export function persistLink( registry, postType, postId, value ) {
	registry
		.dispatch( 'core/editor' )
		.editPost( { meta: { [ META_KEY ]: value } }, { undoIgnore: true } );

	const core = registry.select( 'core' );
	const edits = core.getEntityRecordEdits( 'postType', postType, postId );
	const record = core.getEntityRecord( 'postType', postType, postId );
	if ( record ) {
		registry.dispatch( 'core' ).receiveEntityRecords(
			'postType',
			postType,
			[
				{
					...record,
					meta: { ...record.meta, [ META_KEY ]: value },
				},
			],
			undefined,
			false,
			{ meta: edits?.meta }
		);
	}
}
