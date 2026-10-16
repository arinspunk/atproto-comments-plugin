<?php
/**
 * Grapheme counting and cutting, as AT Protocol counts text limits.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Counts user-perceived characters (extended grapheme clusters) with the most
 * reliable method available:
 *
 * 1. ext-intl (ICU), the same segmentation the PDS uses.
 * 2. PCRE2 `\X`, only if it passes a self-check: PCRE2 10.42 and earlier
 *    join consecutive emoji into one cluster and would count too few.
 * 3. A conservative pattern that may count too many in some scripts (e.g.
 *    loose Hangul jamo), but never joins separate emoji.
 */
final class Graphemes {

	/**
	 * Base character followed by marks, variation selectors, skin tone
	 * modifiers, tags and ZWJ-joined emoji; a pair of regional indicators
	 * (a flag); or CR LF.
	 */
	const FALLBACK = '/\r\n|[\x{1F1E6}-\x{1F1FF}]{2}|.(?:[\p{M}\x{FE00}-\x{FE0F}\x{1F3FB}-\x{1F3FF}\x{E0020}-\x{E007F}]|\x{200D}[\x{2600}-\x{27BF}\x{1F000}-\x{1FAFF}]?)*/su';

	/**
	 * Method in use: 'intl', 'pcre' or 'fallback'. Null until detected.
	 *
	 * @var string|null
	 */
	private static $method = null;

	/**
	 * Number of graphemes, or null for invalid UTF-8.
	 *
	 * @param string $text Text.
	 * @return int|null
	 */
	public static function count( $text ) {
		$text = (string) $text;
		if ( ! self::is_utf8( $text ) ) {
			return null;
		}
		if ( 'intl' === self::method() ) {
			$count = grapheme_strlen( $text );
			return is_int( $count ) ? $count : null;
		}
		return count( self::split( $text ) );
	}

	/**
	 * Cuts a text to at most `$limit` graphemes.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Graphemes.
	 * @return string
	 */
	public static function truncate( $text, $limit ) {
		$text = (string) $text;
		if ( ! self::is_utf8( $text ) ) {
			return '';
		}
		if ( 'intl' === self::method() ) {
			$cut = grapheme_substr( $text, 0, $limit );
			return is_string( $cut ) ? $cut : '';
		}
		return implode( '', array_slice( self::split( $text ), 0, $limit ) );
	}

	/**
	 * Method in use.
	 *
	 * @return string 'intl', 'pcre' or 'fallback'.
	 */
	public static function method() {
		if ( null === self::$method ) {
			if ( function_exists( 'grapheme_strlen' ) && function_exists( 'grapheme_substr' ) ) {
				self::$method = 'intl';
			} elseif ( self::pcre_is_reliable() ) {
				self::$method = 'pcre';
			} else {
				self::$method = 'fallback';
			}
		}
		return self::$method;
	}

	/**
	 * Forces a method (tests only). Null detects it again.
	 *
	 * @param string|null $method 'intl', 'pcre', 'fallback' or null.
	 */
	public static function force( $method ) {
		self::$method = $method;
	}

	/**
	 * Whether PCRE2 `\X` splits consecutive emoji, ZWJ sequences and flags
	 * correctly.
	 *
	 * @return bool
	 */
	public static function pcre_is_reliable() {
		$checks = array(
			"\u{1F600}\u{1F600}"                   => 2, // 😀😀
			"\u{1F469}\u{200D}\u{1F4BB}"           => 1, // 👩‍💻
			"\u{1F469}\u{200D}\u{1F4BB}\u{1F469}\u{200D}\u{1F4BB}" => 2, // 👩‍💻👩‍💻
			"\u{1F1EA}\u{1F1F8}\u{1F1EC}\u{1F1E7}" => 2, // 🇪🇸🇬🇧
			"e\u{0301}"                            => 1, // é with a combining accent.
		);
		foreach ( $checks as $text => $expected ) {
			if ( preg_match_all( '/\X/u', $text ) !== $expected ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Graphemes of a valid UTF-8 text with `\X` or the fallback pattern.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private static function split( $text ) {
		$pattern = 'pcre' === self::method() ? '/\X/u' : self::FALLBACK;
		return preg_match_all( $pattern, $text, $matches ) ? $matches[0] : array();
	}

	/**
	 * Whether a string is valid UTF-8.
	 *
	 * @param string $text Text.
	 * @return bool
	 */
	private static function is_utf8( $text ) {
		return 1 === preg_match( '//u', $text );
	}
}
