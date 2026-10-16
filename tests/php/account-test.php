<?php
/**
 * Tests for the account option and its settings section.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;
use AtprotoComments\Crypto;
use AtprotoComments\Settings;

/**
 * Stored credentials, sanitize callback and settings section.
 */
class Account_Test extends WP_UnitTestCase {

	const PASSWORD = 'abcd-efgh-ijkl-mnop';

	/**
	 * Start every test without an account or pending errors.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Account::OPTION );
		delete_option( Settings::OPTION );
		$GLOBALS['wp_settings_errors'] = array();
	}

	/**
	 * Codes of the account errors added so far.
	 *
	 * @return string[]
	 */
	private function error_codes() {
		return wp_list_pluck( get_settings_errors( Account::OPTION ), 'code' );
	}

	/**
	 * Saves the account the way options.php does.
	 *
	 * @param array $input Submitted fields.
	 */
	private function save( array $input ) {
		update_option( Account::OPTION, $input );
	}

	/**
	 * Saves a valid account.
	 */
	private function save_account() {
		$this->save(
			array(
				'identifier'   => 'usoritual.com',
				'app_password' => self::PASSWORD,
			)
		);
		$GLOBALS['wp_settings_errors'] = array();
	}

	/**
	 * Autoload value of the option in the database.
	 *
	 * @return string|null
	 */
	private function autoload() {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Account::OPTION ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	public function test_not_configured_by_default() {
		$this->assertSame(
			array(
				'identifier' => '',
				'password'   => '',
			),
			Account::get()
		);
		$this->assertFalse( Account::is_configured() );
		$this->assertFalse( Account::is_password_unreadable() );
	}

	public function test_save_account() {
		$this->save(
			array(
				'identifier'   => ' @UsoRitual.com ',
				'app_password' => ' ' . self::PASSWORD . ' ',
			)
		);

		$account = Account::get();
		$this->assertSame( 'usoritual.com', $account['identifier'] );
		$this->assertSame( self::PASSWORD, Crypto::decrypt( $account['password'] ) );
		$this->assertTrue( Account::is_configured() );
		$this->assertSame( array(), $this->error_codes() );
	}

	public function test_first_save_encrypts_once() {
		$this->assertFalse( get_option( Account::OPTION, false ) );
		$this->save_account();

		$this->assertSame( self::PASSWORD, Crypto::decrypt( Account::get()['password'] ) );
	}

	/**
	 * Valid DIDs as identifier.
	 *
	 * @return array
	 */
	public function valid_dids() {
		return array(
			'plc'          => array( 'did:plc:abc234567abcdefghijklmno', 'did:plc:abc234567abcdefghijklmno' ),
			'web'          => array( 'did:web:example.com', 'did:web:example.com' ),
			'web and port' => array( 'did:web:Example.com%3a8443', 'did:web:example.com%3A8443' ),
		);
	}

	/**
	 * A DID is accepted like a handle.
	 *
	 * @dataProvider valid_dids
	 *
	 * @param string $input    Submitted identifier.
	 * @param string $expected Saved identifier.
	 */
	public function test_identifier_with_did( $input, $expected ) {
		$this->save(
			array(
				'identifier'   => $input,
				'app_password' => self::PASSWORD,
			)
		);

		$this->assertSame( $expected, Account::get()['identifier'] );
		$this->assertTrue( Account::is_configured() );
	}

	public function test_no_password_in_database() {
		global $wpdb;
		$this->save_account();

		$found = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", '%' . $wpdb->esc_like( self::PASSWORD ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( '0', $found );
	}

	/**
	 * Identifiers that are not a handle or a DID.
	 *
	 * @return array
	 */
	public function invalid_identifiers() {
		return array(
			'spaces'  => array( 'no es un handle' ),
			'no dot'  => array( 'localhost' ),
			'url'     => array( 'https://bsky.app/profile/usoritual.com' ),
			'did:key' => array( 'did:key:z6Mkabc' ),
			'email'   => array( 'someone@example.com' ),
			'empty'   => array( '' ),
		);
	}

	/**
	 * An invalid identifier adds an error and keeps the saved account.
	 *
	 * @dataProvider invalid_identifiers
	 *
	 * @param string $identifier Submitted identifier.
	 */
	public function test_invalid_identifier_keeps_previous( $identifier ) {
		$this->save_account();
		$before = Account::get();

		$this->save(
			array(
				'identifier'   => $identifier,
				'app_password' => 'other-password',
			)
		);

		$this->assertSame( $before, Account::get() );
		$this->assertSame( array( 'identifier' ), $this->error_codes() );
	}

	public function test_first_save_with_invalid_identifier_adds_a_single_error() {
		$this->save(
			array(
				'identifier'   => 'no es un handle',
				'app_password' => self::PASSWORD,
			)
		);

		$this->assertSame( array( 'identifier' ), $this->error_codes() );
		$this->assertFalse( Account::is_configured() );
	}

	public function test_empty_form_without_account_adds_no_error() {
		$this->save(
			array(
				'identifier'   => '',
				'app_password' => '',
			)
		);

		$this->assertSame( array(), $this->error_codes() );
		$this->assertFalse( Account::is_configured() );
	}

	public function test_save_without_password_keeps_it() {
		$this->save_account();
		$stored = Account::get()['password'];

		$this->save(
			array(
				'identifier'   => 'other.example',
				'app_password' => '',
			)
		);

		$this->assertSame( 'other.example', Account::get()['identifier'] );
		$this->assertSame( $stored, Account::get()['password'] );
		$this->assertSame( self::PASSWORD, Crypto::decrypt( Account::get()['password'] ) );
	}

	public function test_new_password_replaces_the_old_one() {
		$this->save_account();
		$this->save(
			array(
				'identifier'   => 'usoritual.com',
				'app_password' => 'wxyz-wxyz-wxyz-wxyz',
			)
		);

		$this->assertSame( 'wxyz-wxyz-wxyz-wxyz', Crypto::decrypt( Account::get()['password'] ) );
	}

	public function test_submitted_ciphertext_is_not_decrypted_or_reencrypted() {
		$this->save_account();
		$this->save(
			array(
				'identifier' => 'usoritual.com',
				'password'   => 'plain-text',
			)
		);

		$this->assertSame( self::PASSWORD, Crypto::decrypt( Account::get()['password'] ) );
	}

	public function test_forget_account() {
		$this->save_account();
		$this->save( array( 'forget' => '1' ) );

		$this->assertSame(
			array(
				'identifier' => '',
				'password'   => '',
			),
			Account::get()
		);
		$this->assertFalse( Account::is_configured() );
	}

	public function test_changed_auth_key_means_not_configured() {
		$this->save_account();
		$this->assertTrue( Account::is_configured() );

		add_filter(
			'salt',
			static function ( $salt ) {
				return $salt . 'rotated';
			}
		);

		$this->assertFalse( Account::is_configured() );
		$this->assertTrue( Account::is_password_unreadable() );
	}

	public function test_option_is_not_autoloaded_after_first_save() {
		$this->assertNull( $this->autoload() );
		$this->save_account();

		// 6.5 stores 'no'; 6.6+ stores 'off'.
		$this->assertContains( $this->autoload(), array( 'no', 'off' ) );

		$this->save(
			array(
				'identifier'   => 'other.example',
				'app_password' => '',
			)
		);
		$this->assertContains( $this->autoload(), array( 'no', 'off' ) );
	}

	public function test_errors_are_independent_from_display_settings() {
		// options.php saves each option of the group in turn.
		update_option(
			Settings::OPTION,
			array(
				'appview'    => 'https://public.api.bsky.app',
				'max_depth'  => '50',
				'position'   => 'replace',
				'post_types' => array( 'post' ),
			)
		);
		$this->save(
			array(
				'identifier'   => 'usoritual.com',
				'app_password' => self::PASSWORD,
			)
		);

		$this->assertSame( array( 'max_depth' ), wp_list_pluck( get_settings_errors( Settings::OPTION ), 'code' ) );
		$this->assertSame( array(), $this->error_codes() );
		$this->assertTrue( Account::is_configured() );
	}

	public function test_forget_form_keeps_display_settings() {
		update_option( Settings::OPTION, array( 'max_depth' => '8' ) );
		$this->save_account();

		// The forget form only posts the account; the settings get null.
		update_option( Settings::OPTION, null );
		$this->save( array( 'forget' => '1' ) );

		$this->assertSame( 8, Settings::get()['max_depth'] );
		$this->assertFalse( Account::is_configured() );
	}

	/**
	 * Renders the settings page sections and the forget form.
	 *
	 * @return string
	 */
	private function render_settings() {
		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$GLOBALS['wp_settings_sections'] = array();
		$GLOBALS['wp_settings_fields']   = array();
		Settings::add_fields();
		Account::add_fields();

		ob_start();
		do_settings_sections( Settings::PAGE );
		Account::render_forget_form();
		return ob_get_clean();
	}

	public function test_settings_html_never_contains_the_password() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->save_account();

		$html = $this->render_settings();

		$this->assertStringContainsString( 'value="usoritual.com"', $html );
		$this->assertStringNotContainsString( self::PASSWORD, $html );
		$this->assertStringNotContainsString( Account::get()['password'], $html );
		$this->assertStringNotContainsString( 'v1:', $html );
		$this->assertMatchesRegularExpression( '/<input type="password"[^>]*name="atproto_comments_account\[app_password\]"[^>]*value=""[^>]*autocomplete="new-password"/', $html );
		$this->assertStringContainsString( 'An app password is saved', $html );
		$this->assertStringContainsString( 'form="atproto-comments-forget-account"', $html );
		$this->assertStringContainsString( 'name="atproto_comments_account[forget]"', $html );
		$this->assertStringContainsString( 'aria-live="polite"', $html );
		$this->assertStringContainsString( 'atproto_comments_publish_capability', $html );
	}

	public function test_settings_html_without_account() {
		$html = $this->render_settings();

		$this->assertStringContainsString( 'No app password is saved', $html );
		$this->assertStringNotContainsString( 'form="atproto-comments-forget-account"', $html );
	}

	public function test_settings_html_warns_when_password_cannot_be_decrypted() {
		$this->save_account();
		add_filter(
			'salt',
			static function ( $salt ) {
				return $salt . 'rotated';
			}
		);

		$html = $this->render_settings();

		$this->assertStringContainsString( 'can no longer be decrypted', $html );
		$this->assertStringContainsString( 'No app password is saved', $html );
	}
}
