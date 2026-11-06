<?php
/**
 * Translatable UI strings of the web component.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the `strings` attribute of <atproto-comments>.
 */
final class UI_Strings {

	/**
	 * English source of each key, identical to the component's own dictionary.
	 */
	const SOURCE = array(
		'comment'          => 'Comment',
		'reply'            => 'Reply',
		'replyTo'          => 'Reply to {name}',
		'commentIn'        => 'Comment in…',
		'replyIn'          => 'Reply to {name} in…',
		'close'            => 'Close',
		'lastUsed'         => 'Last used',
		'otherApp'         => 'Other app',
		'inputPlaceholder' => 'app.example',
		'go'               => 'Open',
		'invalidDomain'    => 'Invalid domain',
		'error'            => 'Comments couldn\'t be loaded.',
		'viewMore'         => 'View more replies',
		'unknownAuthor'    => 'Unknown',
		'now'              => 'now',
		'minutes'          => 'm',
		'hours'            => 'h',
		'days'             => 'd',
		'months'           => 'mo',
	);

	/**
	 * All UI strings, translated.
	 *
	 * @return array<string, string>
	 */
	public static function all() {
		return array(
			'comment'          => __( 'Comment', 'parolas' ),
			'reply'            => __( 'Reply', 'parolas' ),
			/* translators: Accessible label of the reply button. {name} is the author's name; keep it as is. */
			'replyTo'          => __( 'Reply to {name}', 'parolas' ),
			/* translators: Heading of the panel where visitors choose the app to comment with. It ends in an ellipsis because the app names follow. */
			'commentIn'        => __( 'Comment in…', 'parolas' ),
			/* translators: Heading of the panel where visitors choose the app to reply with. {name} is the author's name; keep it as is. It ends in an ellipsis because the app names follow. */
			'replyIn'          => __( 'Reply to {name} in…', 'parolas' ),
			'close'            => _x( 'Close', 'close the app picker', 'parolas' ),
			/* translators: Badge next to the app the visitor chose last time. */
			'lastUsed'         => __( 'Last used', 'parolas' ),
			'otherApp'         => __( 'Other app', 'parolas' ),
			/* translators: Placeholder of the field where visitors type the domain of their AT Protocol app. */
			'inputPlaceholder' => __( 'app.example', 'parolas' ),
			/* translators: Accessible name of the arrow button that opens the app typed in the "Other app" field. */
			'go'               => _x( 'Open', 'open the app typed in Other app', 'parolas' ),
			'invalidDomain'    => __( 'Invalid domain', 'parolas' ),
			'error'            => __( 'Comments couldn\'t be loaded.', 'parolas' ),
			'viewMore'         => __( 'View more replies', 'parolas' ),
			'unknownAuthor'    => _x( 'Unknown', 'name shown for a reply author with no name or handle', 'parolas' ),
			'now'              => _x( 'now', 'relative time under one minute', 'parolas' ),
			/* translators: Abbreviation of "minutes", shown after a number as in "5 m". */
			'minutes'          => _x( 'm', 'abbreviation of minutes', 'parolas' ),
			/* translators: Abbreviation of "hours", shown after a number as in "5 h". */
			'hours'            => _x( 'h', 'abbreviation of hours', 'parolas' ),
			/* translators: Abbreviation of "days", shown after a number as in "5 d". */
			'days'             => _x( 'd', 'abbreviation of days', 'parolas' ),
			/* translators: Abbreviation of "months", shown after a number as in "5 mo". */
			'months'           => _x( 'mo', 'abbreviation of months', 'parolas' ),
		);
	}

	/**
	 * Only the strings whose translation differs from the English source, so
	 * the rest come from the component's own dictionary for `lang`.
	 *
	 * @return array<string, string>
	 */
	public static function translated() {
		$out = array();
		foreach ( self::all() as $key => $text ) {
			if ( self::SOURCE[ $key ] !== $text ) {
				$out[ $key ] = $text;
			}
		}
		return $out;
	}
}
