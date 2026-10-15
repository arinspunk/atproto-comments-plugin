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
		'otherApp'         => 'Other app',
		'inputPlaceholder' => 'app.example',
		'invalidDomain'    => 'Invalid domain',
		'error'            => 'Comments couldn\'t be loaded.',
		'viewMore'         => 'View more on Bluesky',
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
			'comment'          => __( 'Comment', 'atproto-comments' ),
			'reply'            => __( 'Reply', 'atproto-comments' ),
			/* translators: Accessible label of the reply button. {name} is the author's name; keep it as is. */
			'replyTo'          => __( 'Reply to {name}', 'atproto-comments' ),
			'otherApp'         => __( 'Other app', 'atproto-comments' ),
			/* translators: Placeholder of the field where visitors type the domain of their AT Protocol app. */
			'inputPlaceholder' => __( 'app.example', 'atproto-comments' ),
			'invalidDomain'    => __( 'Invalid domain', 'atproto-comments' ),
			'error'            => __( 'Comments couldn\'t be loaded.', 'atproto-comments' ),
			'viewMore'         => __( 'View more on Bluesky', 'atproto-comments' ),
			'now'              => _x( 'now', 'relative time under one minute', 'atproto-comments' ),
			/* translators: Abbreviation of "minutes", shown after a number as in "5 m". */
			'minutes'          => _x( 'm', 'abbreviation of minutes', 'atproto-comments' ),
			/* translators: Abbreviation of "hours", shown after a number as in "5 h". */
			'hours'            => _x( 'h', 'abbreviation of hours', 'atproto-comments' ),
			/* translators: Abbreviation of "days", shown after a number as in "5 d". */
			'days'             => _x( 'd', 'abbreviation of days', 'atproto-comments' ),
			/* translators: Abbreviation of "months", shown after a number as in "5 mo". */
			'months'           => _x( 'mo', 'abbreviation of months', 'atproto-comments' ),
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
