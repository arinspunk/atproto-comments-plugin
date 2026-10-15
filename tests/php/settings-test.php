<?php
/**
 * Tests for Settings.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Settings;

/**
 * Defaults and validation of the settings.
 */
class Settings_Test extends WP_UnitTestCase {

	/**
	 * Start every test without saved settings or pending errors.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION );
		$GLOBALS['wp_settings_errors'] = array();
	}

	/**
	 * Codes of the settings errors added so far.
	 *
	 * @return string[]
	 */
	private function error_codes() {
		return wp_list_pluck( get_settings_errors( Settings::OPTION ), 'code' );
	}

	/**
	 * Saves valid settings so later tests have a previous value.
	 */
	private function save_previous() {
		update_option(
			Settings::OPTION,
			array(
				'appview'    => 'https://previous.example',
				'max_depth'  => '8',
				'position'   => 'after_content',
				'post_types' => array( 'post', 'page' ),
			)
		);
		$GLOBALS['wp_settings_errors'] = array();
	}

	public function test_defaults() {
		$this->assertSame(
			array(
				'appview'    => 'https://public.api.bsky.app',
				'max_depth'  => 6,
				'position'   => 'replace',
				'post_types' => array( 'post' ),
			),
			Settings::get()
		);
		$this->assertSame( array( 'post' ), Settings::active_post_types() );
	}

	public function test_valid_values_are_saved() {
		$this->save_previous();
		$this->assertSame(
			array(
				'appview'    => 'https://previous.example',
				'max_depth'  => 8,
				'position'   => 'after_content',
				'post_types' => array( 'post', 'page' ),
			),
			get_option( Settings::OPTION )
		);
		$this->assertSame( array(), $this->error_codes() );
	}

	public function test_appview_trailing_slash_is_removed() {
		$out = Settings::sanitize( array( 'appview' => ' https://appview.example// ' ) );
		$this->assertSame( 'https://appview.example', $out['appview'] );
		$this->assertSame( array(), $this->error_codes() );
	}

	/**
	 * Invalid AppView values.
	 *
	 * @return array
	 */
	public function invalid_appviews() {
		return array(
			'http'       => array( 'http://appview.example' ),
			'not a url'  => array( 'appview.example' ),
			'empty'      => array( '' ),
			'ftp'        => array( 'ftp://appview.example' ),
			'javascript' => array( 'javascript:alert(1)' ),
		);
	}

	/**
	 * An invalid AppView keeps the previous value and adds an error.
	 *
	 * @dataProvider invalid_appviews
	 *
	 * @param string $appview Value.
	 */
	public function test_invalid_appview_keeps_previous( $appview ) {
		$this->save_previous();
		$out = Settings::sanitize( array( 'appview' => $appview ) );
		$this->assertSame( 'https://previous.example', $out['appview'] );
		$this->assertSame( array( 'appview' ), $this->error_codes() );
	}

	/**
	 * Valid depths.
	 *
	 * @return array
	 */
	public function valid_depths() {
		return array(
			'1'      => array( '1', 1 ),
			'20'     => array( '20', 20 ),
			'int 12' => array( 12, 12 ),
		);
	}

	/**
	 * Valid depths are saved as integers.
	 *
	 * @dataProvider valid_depths
	 *
	 * @param mixed $depth    Value.
	 * @param int   $expected Saved value.
	 */
	public function test_valid_depth( $depth, $expected ) {
		$out = Settings::sanitize( array( 'max_depth' => $depth ) );
		$this->assertSame( $expected, $out['max_depth'] );
		$this->assertSame( array(), $this->error_codes() );
	}

	/**
	 * Invalid depths.
	 *
	 * @return array
	 */
	public function invalid_depths() {
		return array(
			'0'        => array( '0' ),
			'50'       => array( '50' ),
			'21'       => array( '21' ),
			'negative' => array( '-3' ),
			'decimal'  => array( '6.5' ),
			'text'     => array( 'six' ),
			'empty'    => array( '' ),
		);
	}

	/**
	 * An invalid depth keeps the previous value and adds an error.
	 *
	 * @dataProvider invalid_depths
	 *
	 * @param string $depth Value.
	 */
	public function test_invalid_depth_keeps_previous( $depth ) {
		$this->save_previous();
		$out = Settings::sanitize( array( 'max_depth' => $depth ) );
		$this->assertSame( 8, $out['max_depth'] );
		$this->assertSame( array( 'max_depth' ), $this->error_codes() );
	}

	public function test_position() {
		$out = Settings::sanitize( array( 'position' => 'after_content' ) );
		$this->assertSame( 'after_content', $out['position'] );

		$this->save_previous();
		$out = Settings::sanitize( array( 'position' => 'sidebar' ) );
		$this->assertSame( 'after_content', $out['position'] );
	}

	public function test_post_types_only_existing_public_types() {
		register_post_type( 'atproto_private', array( 'public' => false ) );
		$out = Settings::sanitize( array( 'post_types' => array( 'page', 'attachment', 'nope', 'atproto_private', 'post' ) ) );
		$this->assertSame( array( 'post', 'page' ), $out['post_types'] );
		$this->assertArrayNotHasKey( 'attachment', Settings::available_post_types() );
	}

	public function test_missing_post_types_saves_empty_list() {
		$this->save_previous();
		update_option(
			Settings::OPTION,
			array(
				'appview'   => 'https://previous.example',
				'max_depth' => '8',
				'position'  => 'replace',
			)
		);
		$this->assertSame( array(), get_option( Settings::OPTION )['post_types'] );
		$this->assertSame( array(), Settings::active_post_types() );
	}

	public function test_first_save_with_invalid_depth_adds_a_single_error() {
		$this->assertFalse( get_option( Settings::OPTION, false ) );

		update_option(
			Settings::OPTION,
			array(
				'appview'    => 'https://appview.example/',
				'max_depth'  => '50',
				'position'   => 'after_content',
				'post_types' => array( 'page' ),
			)
		);

		$this->assertSame( array( 'max_depth' ), $this->error_codes() );
		$this->assertSame(
			array(
				'appview'    => 'https://appview.example',
				'max_depth'  => 6,
				'position'   => 'after_content',
				'post_types' => array( 'page' ),
			),
			get_option( Settings::OPTION )
		);
	}
}
