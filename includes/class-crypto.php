<?php
/**
 * Encryption of the app password.
 *
 * Uses sodium secretbox, which WordPress guarantees through sodium_compat even
 * without the sodium extension.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts and decrypts secrets with a key derived from the auth salt.
 */
final class Crypto {

	const PREFIX = 'v1:';

	const CONTEXT = 'atproto-comments-app-password-v1';

	/**
	 * Encrypts a value.
	 *
	 * @param string $plaintext Value.
	 * @return string `v1:` + base64( nonce ‖ ciphertext ).
	 */
	public static function encrypt( $plaintext ) {
		$key    = self::key();
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( (string) $plaintext, $nonce, $key );
		self::wipe( $key );
		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext stored as text.
	}

	/**
	 * Decrypts a value stored by encrypt().
	 *
	 * @param mixed $stored Stored value.
	 * @return string|null The plaintext, or null if the value is missing, has
	 *                     an unknown format or cannot be decrypted (e.g. the
	 *                     salt changed).
	 */
	public static function decrypt( $stored ) {
		if ( ! is_string( $stored ) || 0 !== strpos( $stored, self::PREFIX ) ) {
			return null;
		}
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary ciphertext stored as text.
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
			return null;
		}

		$key = self::key();
		try {
			$plaintext = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				$key
			);
		} catch ( \Exception $e ) {
			$plaintext = false;
		}
		self::wipe( $key );

		return false === $plaintext ? null : $plaintext;
	}

	/**
	 * Wipes a secret from memory. sodium_compat's sodium_memzero() throws
	 * without the extension, so it is only called when the extension is
	 * loaded; otherwise the variable is set to null.
	 *
	 * @param mixed $value Variable to wipe, by reference.
	 */
	public static function wipe( &$value ) {
		if ( is_string( $value ) && extension_loaded( 'sodium' ) ) {
			sodium_memzero( $value );
		}
		$value = null;
	}

	/**
	 * Encryption key: 32 bytes derived from the auth salt (AUTH_KEY and
	 * AUTH_SALT, or the generated salts).
	 *
	 * @return string
	 */
	private static function key() {
		return hash_hmac( 'sha256', self::CONTEXT, wp_salt( 'auth' ), true );
	}
}
