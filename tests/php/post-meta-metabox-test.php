<?php
/**
 * Tests for the classic editor metabox save.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Post_Meta;

/**
 * Saving through the metabox.
 */
class Post_Meta_Metabox_Test extends WP_UnitTestCase {

	use Atproto_Comments_Network_Mock;

	const URI = 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u';

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private $post_id;

	public function set_up() {
		parent::set_up();
		Post_Meta::register();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->post_id = self::factory()->post->create();
		update_post_meta( $this->post_id, Post_Meta::KEY, self::URI );
	}

	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Simulates the classic editor form.
	 *
	 * @param string $value Field value.
	 * @param bool   $nonce Whether to send the nonce.
	 */
	private function submit( $value, $nonce = true ) {
		$_POST = array( Post_Meta::FIELD => wp_slash( $value ) );
		if ( $nonce ) {
			$_POST[ Post_Meta::NONCE ] = wp_create_nonce( 'atproto_comments_save_thread' );
		}
		wp_update_post(
			array(
				'ID'         => $this->post_id,
				'post_title' => 'Updated',
			)
		);
	}

	/**
	 * Pending notice of the current user.
	 *
	 * @return mixed
	 */
	private function notice() {
		return get_transient( 'atproto_comments_notice_' . get_current_user_id() );
	}

	public function test_app_url_is_saved_as_at_uri() {
		$this->submit( ' https://bsky.app/profile/did:plc:abc234567abcdefghijklmno/post/other/ ' );
		$this->assertSame( 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/other', get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->assertFalse( $this->notice() );
	}

	public function test_did_web_with_port_is_kept() {
		$this->submit( 'at://did:web:localhost%3A8080/app.bsky.feed.post/3mwso7oovnp2u' );
		$this->assertSame( 'at://did:web:localhost%3A8080/app.bsky.feed.post/3mwso7oovnp2u', get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
	}

	public function test_resolvable_handle_is_saved_with_did() {
		$this->mock_network( array(), array( '_atproto.usoritual.com' => array( 'did=did:plc:zzz234567abcdefghijklmno' ) ) );

		$this->submit( 'https://bsky.app/profile/usoritual.com/post/other' );

		$this->assertSame( 'at://did:plc:zzz234567abcdefghijklmno/app.bsky.feed.post/other', get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->assertFalse( $this->notice() );
	}

	public function test_unresolved_handle_keeps_previous_and_sets_notice() {
		$this->mock_network( array() );

		$this->submit( 'https://bsky.app/profile/no-existe.example/post/3mwso7oovnp2u' );

		$this->assertSame( self::URI, get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->assertSame( 'handle_unresolved', $this->notice() );
		$this->assertStringContainsString( 'could not be resolved', Post_Meta::error_message( 'handle_unresolved' ) );
	}

	public function test_invalid_keeps_previous_and_sets_notice() {
		$this->submit( 'hola' );
		$this->assertSame( self::URI, get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->assertSame( 'format', $this->notice() );
	}

	public function test_empty_unlinks() {
		$this->submit( '' );
		$this->assertSame( '', get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->assertFalse( $this->notice() );
	}

	public function test_without_nonce_does_nothing() {
		$this->submit( 'hola', false );
		$this->assertSame( self::URI, get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->assertFalse( $this->notice() );
	}

	public function test_invalid_nonce_does_nothing() {
		$_POST = array(
			Post_Meta::FIELD => '',
			Post_Meta::NONCE => 'bad',
		);
		wp_update_post( array( 'ID' => $this->post_id ) );
		$this->assertSame( self::URI, get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
	}

	public function test_admin_notice_is_shown_once() {
		$this->submit( 'hola' );
		ob_start();
		Post_Meta::admin_notices();
		$this->assertStringContainsString( 'notice-error', ob_get_clean() );
		ob_start();
		Post_Meta::admin_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * DOING_AUTOSAVE cannot be undefined, so this runs in its own process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_autosave_does_nothing() {
		define( 'DOING_AUTOSAVE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core constant.
		$this->submit( 'hola' );
		$this->assertSame( self::URI, get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->assertFalse( $this->notice() );
	}
}
