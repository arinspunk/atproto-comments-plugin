<?php
/**
 * Tests for Crypto.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Crypto;

/**
 * Encryption of the app password.
 */
class Crypto_Test extends WP_UnitTestCase {

	const PASSWORD = 'abcd-efgh-ijkl-mnop';

	public function test_round_trip() {
		$stored = Crypto::encrypt( self::PASSWORD );
		$this->assertSame( self::PASSWORD, Crypto::decrypt( $stored ) );
	}

	public function test_stored_value_is_not_plaintext() {
		$stored = Crypto::encrypt( self::PASSWORD );
		$this->assertStringStartsWith( 'v1:', $stored );
		$this->assertStringNotContainsString( self::PASSWORD, $stored );
		$this->assertStringNotContainsString( self::PASSWORD, base64_decode( substr( $stored, 3 ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	public function test_each_encryption_uses_a_new_nonce() {
		$this->assertNotSame( Crypto::encrypt( self::PASSWORD ), Crypto::encrypt( self::PASSWORD ) );
	}

	public function test_changed_salt_returns_null() {
		$stored = Crypto::encrypt( self::PASSWORD );

		add_filter(
			'salt',
			static function ( $salt ) {
				return $salt . 'rotated';
			}
		);

		$this->assertNull( Crypto::decrypt( $stored ) );
	}

	/**
	 * Values that cannot be decrypted.
	 *
	 * @return array
	 */
	public function invalid_values() {
		return array(
			'null'          => array( null ),
			'empty'         => array( '' ),
			'array'         => array( array( 'x' ) ),
			'no prefix'     => array( 'abcd-efgh-ijkl-mnop' ),
			'other version' => array( 'v2:' . base64_encode( str_repeat( 'x', 60 ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'not base64'    => array( 'v1:***' ),
			'too short'     => array( 'v1:' . base64_encode( 'short' ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'tampered'      => array( 'v1:' . base64_encode( str_repeat( 'x', 60 ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
	}

	/**
	 * Unknown or broken values return null without errors.
	 *
	 * @dataProvider invalid_values
	 *
	 * @param mixed $value Stored value.
	 */
	public function test_invalid_value_returns_null( $value ) {
		$this->assertNull( Crypto::decrypt( $value ) );
	}

	public function test_wipe() {
		$secret = 'secret-' . wp_generate_password( 12, false );
		Crypto::wipe( $secret );
		$this->assertNull( $secret );

		$missing = null;
		Crypto::wipe( $missing );
		$this->assertNull( $missing );
	}

	/**
	 * Without the sodium extension, WordPress provides the functions through
	 * sodium_compat, whose sodium_memzero() throws. Runs Crypto in a PHP
	 * process started without extensions (`php -n`) and the polyfill loaded.
	 */
	public function test_works_with_sodium_compat_without_extension() {
		if ( ! function_exists( 'exec' ) || ! defined( 'PHP_BINARY' ) || '' === PHP_BINARY ) {
			$this->markTestSkipped( 'Cannot start a PHP process.' );
		}

		$script = wp_tempnam( 'atproto-comments-crypto' );
		$code   = <<<'PHP'
<?php
define( 'ABSPATH', $argv[1] );
if ( extension_loaded( 'sodium' ) ) {
	echo json_encode( array( 'skip' => true ) );
	exit;
}
require ABSPATH . 'wp-includes/sodium_compat/autoload.php';
function wp_salt( $scheme ) {
	return 'test-salt-' . $scheme;
}
require $argv[2];
$result = array( 'skip' => false );
try {
	$stored             = AtprotoComments\Crypto::encrypt( 'abcd-efgh-ijkl-mnop' );
	$result['plain']    = false !== strpos( $stored, 'abcd-efgh-ijkl-mnop' );
	$result['decrypt']  = AtprotoComments\Crypto::decrypt( $stored );
	$result['tampered'] = AtprotoComments\Crypto::decrypt( 'v1:' . base64_encode( str_repeat( 'x', 60 ) ) );
	$secret             = 'abcd-efgh-ijkl-mnop';
	AtprotoComments\Crypto::wipe( $secret );
	$result['wiped']    = null === $secret;
} catch ( Throwable $e ) {
	$result['exception'] = get_class( $e ) . ': ' . $e->getMessage();
}
echo json_encode( $result );
PHP;
		file_put_contents( $script, $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$output = array();
		exec( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( $script ) . ' '
			. escapeshellarg( ABSPATH ) . ' '
			. escapeshellarg( dirname( __DIR__, 2 ) . '/includes/class-crypto.php' ),
			$output
		);
		unlink( $script ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result, implode( "\n", $output ) );
		if ( $result['skip'] ) {
			$this->markTestSkipped( 'The sodium extension is built into this PHP.' );
		}
		$this->assertArrayNotHasKey( 'exception', $result );
		$this->assertFalse( $result['plain'] );
		$this->assertSame( 'abcd-efgh-ijkl-mnop', $result['decrypt'] );
		$this->assertNull( $result['tampered'] );
		$this->assertTrue( $result['wiped'] );
	}
}
