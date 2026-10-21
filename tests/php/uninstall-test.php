<?php
/**
 * Tests for uninstall.php.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;
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
			define( 'WP_UNINSTALL_PLUGIN', 'parolas/parolas.php' );
		}
		include dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/**
	 * Saves an account, a lock and a cached handle on the current site.
	 */
	private function add_plugin_data() {
		update_option(
			Account::OPTION,
			array(
				'identifier'   => 'usoritual.com',
				'app_password' => 'abcd-efgh-ijkl-mnop',
			)
		);
		add_option( 'atproto_comments_lock_12', time(), '', false );
		set_transient( 'atproto_comments_handle_' . md5( 'usoritual.com' ), 'did:plc:abc234567abcdefghijklmno', 10 * MINUTE_IN_SECONDS );
		// A similar name that is not ours stays.
		add_option( 'atproto_commentsXlock_1', 'keep' );
	}

	/**
	 * Plugin rows left in the options table of the current site.
	 *
	 * @return string[]
	 */
	private function plugin_rows() {
		global $wpdb;
		return $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name",
				'%' . $wpdb->esc_like( 'atproto_comments' ) . '%',
				'%' . $wpdb->esc_like( 'atproto_comments_handle_' ) . '%'
			)
		);
	}

	public function test_deletes_settings_and_keeps_meta() {
		update_option( Settings::OPTION, array( 'max_depth' => '3' ) );
		$this->add_plugin_data();
		$this->assertCount( 6, $this->plugin_rows() );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_atproto_thread_uri', 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u' );

		$this->uninstall();

		$this->assertFalse( get_option( Settings::OPTION, false ) );
		$this->assertFalse( get_option( Account::OPTION, false ) );
		$this->assertSame( array( 'atproto_commentsXlock_1' ), $this->plugin_rows() );
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
		$this->add_plugin_data();
		switch_to_blog( $second );
		update_option( Settings::OPTION, array( 'max_depth' => '4' ) );
		$this->add_plugin_data();
		$this->assertNotFalse( get_option( Settings::OPTION, false ) );
		restore_current_blog();

		$this->uninstall();

		$this->assertFalse( get_option( Settings::OPTION, false ) );
		$this->assertSame( array( 'atproto_commentsXlock_1' ), $this->plugin_rows() );
		switch_to_blog( $second );
		$this->assertFalse( get_option( Settings::OPTION, false ) );
		$this->assertFalse( get_option( Account::OPTION, false ) );
		$this->assertSame( array( 'atproto_commentsXlock_1' ), $this->plugin_rows() );
		restore_current_blog();
	}
}
