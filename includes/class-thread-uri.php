<?php
/**
 * Thread link normalization and validation.
 *
 * Pure PHP, without WordPress functions. Mirrors src/editor/thread-uri.js;
 * both are tested against tests/fixtures/thread-uris.json.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes an at:// URI or an app URL with a DID to the canonical at:// form.
 */
final class Thread_Uri {

	const DID = 'did:plc:[a-z2-7]{24}|did:web:[a-zA-Z0-9.-]+(?:%3A[0-9]+)?';

	const RKEY = '(?!\.\.?$)[A-Za-z0-9._~:-]{1,512}';

	const HANDLE = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+';

	const COLLECTION = 'app.bsky.feed.post';

	/**
	 * Pattern for the stored value: the canonical at:// URI or an empty string.
	 * Valid both as PCRE and as an ECMAScript (JSON Schema) pattern.
	 */
	const PATTERN = '^(?:at://(?:did:plc:[a-z2-7]{24}|did:web:[a-zA-Z0-9.-]+(?:%3A[0-9]+)?)/app\.bsky\.feed\.post/(?!\.\.?$)[A-Za-z0-9._~:-]{1,512})?$';

	/**
	 * Normalizes a thread link.
	 *
	 * @param string $value Raw input.
	 * @return array{ok: bool, uri?: string, error?: string} On success `uri` is
	 *               the canonical at:// URI ('' unlinks); on failure `error` is
	 *               'handle' or 'format'.
	 */
	public static function normalize( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return array(
				'ok'  => true,
				'uri' => '',
			);
		}

		if ( preg_match( '~^at://([^/]+)/app\.bsky\.feed\.post/([^/?#]*)\z~', $value, $m )
			|| preg_match( '~^https://[A-Za-z0-9.-]+(?::[0-9]+)?/profile/([^/?#]+)/post/([^/?#]+)/?(?:[?#].*)?\z~s', $value, $m )
		) {
			list( , $authority, $rkey ) = $m;

			if ( preg_match( '/^' . self::RKEY . '\z/', $rkey ) ) {
				if ( preg_match( '/^(?:' . self::DID . ')\z/', $authority ) ) {
					return array(
						'ok'  => true,
						'uri' => 'at://' . $authority . '/' . self::COLLECTION . '/' . $rkey,
					);
				}
				if ( 0 !== strpos( $authority, 'did:' ) && preg_match( '/^' . self::HANDLE . '\z/', $authority ) ) {
					return array(
						'ok'    => false,
						'error' => 'handle',
					);
				}
			}
		}

		return array(
			'ok'    => false,
			'error' => 'format',
		);
	}

	/**
	 * Whether a stored value is a canonical, non-empty thread URI.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_canonical( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return false;
		}
		$result = self::normalize( $value );
		return $result['ok'] && $result['uri'] === $value;
	}
}
