<?php
/**
 * Tests for uninstall.php.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Settings;

/**
 * Uninstall deletes the settings and keeps post links.
 */
class Uninstall_Test extends WP_UnitTestCase {

	/**
	 * Runs uninstall.php.
	 */
	private function uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'atproto-comments/atproto-comments.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	public function test_deletes_settings_and_keeps_meta() {
		update_option( Settings::OPTION, array( 'max_depth' => '3' ) );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_atproto_thread_uri', 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u' );

		$this->uninstall();

		$this->assertFalse( get_option( Settings::OPTION, false ) );
		$this->assertSame( 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u', get_post_meta( $post_id, '_atproto_thread_uri', true ) );
	}

	/**
	 * Run with `npm run test:php:multisite`.
	 *
	 * @group multisite
	 */
	public function test_deletes_settings_on_every_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}

		$second = self::factory()->blog->create();
		update_option( Settings::OPTION, array( 'max_depth' => '3' ) );
		switch_to_blog( $second );
		update_option( Settings::OPTION, array( 'max_depth' => '4' ) );
		$this->assertNotFalse( get_option( Settings::OPTION, false ) );
		restore_current_blog();

		$this->uninstall();

		$this->assertFalse( get_option( Settings::OPTION, false ) );
		switch_to_blog( $second );
		$this->assertFalse( get_option( Settings::OPTION, false ) );
		restore_current_blog();
	}
}
