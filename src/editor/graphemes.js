/**
 * Grapheme counting for the post text, as AT Protocol counts its limits.
 *
 * Uses Intl.Segmenter. Without it, counts code points, which counts too many
 * (e.g. 👩‍💻 is 3), so the panel never accepts a text the server rejects.
 */

/**
 * Number of code points: the fallback count.
 *
 * @param {string} text Text.
 * @return {number} Code points.
 */
export function countCodePoints( text ) {
	return Array.from( String( text ?? '' ) ).length;
}

/**
 * Number of graphemes (user-perceived characters).
 *
 * @param {string} text Text.
 * @return {number} Graphemes.
 */
export function countGraphemes( text ) {
	const value = String( text ?? '' );
	const Segmenter = typeof Intl !== 'undefined' ? Intl.Segmenter : undefined;
	if ( typeof Segmenter !== 'function' ) {
		return countCodePoints( value );
	}
	let count = 0;
	// eslint-disable-next-line no-unused-vars
	for ( const segment of new Segmenter( undefined, {
		granularity: 'grapheme',
	} ).segment( value ) ) {
		count++;
	}
	return count;
}
