<?php
/**
 * REST tests for the thread link meta.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Post_Meta;
use AtprotoComments\Settings;

/**
 * Writing `_atproto_thread_uri` through the REST API.
 */
class Post_Meta_Rest_Test extends WP_Test_REST_TestCase {

	const URI = 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u';

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	private static $editor;

	/**
	 * Contributor user ID.
	 *
	 * @var int
	 */
	private static $contributor;

	/**
	 * Creates the users.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$editor      = $factory->user->create( array( 'role' => 'editor' ) );
		self::$contributor = $factory->user->create( array( 'role' => 'contributor' ) );
	}

	/**
	 * The test suite resets post types (and their meta) after each test.
	 */
	public function set_up() {
		parent::set_up();
		Post_Meta::register();
	}

	/**
	 * Updates a post through REST.
	 *
	 * @param string $route Route.
	 * @param mixed  $value Meta value.
	 * @return WP_REST_Response
	 */
	private function update( $route, $value ) {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_body_params( array( 'meta' => array( Post_Meta::KEY => $value ) ) );
		return rest_get_server()->dispatch( $request );
	}

	public function test_meta_is_registered_for_posts_only_by_default() {
		$this->assertArrayHasKey( Post_Meta::KEY, get_registered_meta_keys( 'post', 'post' ) );
		$this->assertArrayNotHasKey( Post_Meta::KEY, get_registered_meta_keys( 'post', 'page' ) );
	}

	public function test_editor_saves_canonical_uri() {
		wp_set_current_user( self::$editor );
		$post_id  = self::factory()->post->create();
		$response = $this->update( "/wp/v2/posts/$post_id", self::URI );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::URI, get_post_meta( $post_id, Post_Meta::KEY, true ) );
		$this->assertSame( self::URI, $response->get_data()['meta'][ Post_Meta::KEY ] );
	}

	public function test_empty_value_unlinks() {
		wp_set_current_user( self::$editor );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Post_Meta::KEY, self::URI );

		$response = $this->update( "/wp/v2/posts/$post_id", '' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', Post_Meta::get( $post_id ) );
	}

	/**
	 * Non-canonical values.
	 *
	 * @return array
	 */
	public function non_canonical_values() {
		return array(
			'app url'    => array( 'https://bsky.app/profile/did:plc:abc234567abcdefghijklmno/post/3mwso7oovnp2u' ),
			'handle'     => array( 'at://usoritual.com/app.bsky.feed.post/3mwso7oovnp2u' ),
			'collection' => array( 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.like/3mwso7oovnp2u' ),
			'spaces'     => array( ' ' . self::URI ),
			'text'       => array( 'hola' ),
		);
	}

	/**
	 * A non-canonical value returns 400 and does not change the meta.
	 *
	 * @dataProvider non_canonical_values
	 *
	 * @param string $value Value.
	 */
	public function test_non_canonical_value_is_rejected( $value ) {
		wp_set_current_user( self::$editor );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Post_Meta::KEY, self::URI );

		$response = $this->update( "/wp/v2/posts/$post_id", $value );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( self::URI, get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}

	public function test_user_without_edit_post_is_rejected() {
		wp_set_current_user( self::$contributor );
		$post_id = self::factory()->post->create( array( 'post_author' => self::$editor ) );
		update_post_meta( $post_id, Post_Meta::KEY, self::URI );

		$response = $this->update( "/wp/v2/posts/$post_id", '' );

		$this->assertContains( $response->get_status(), array( 401, 403 ) );
		$this->assertSame( self::URI, get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}

	public function test_meta_auth_callback_requires_edit_post() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$editor ) );

		wp_set_current_user( self::$contributor );
		$this->assertFalse( current_user_can( 'edit_post_meta', $post_id, Post_Meta::KEY ) );

		wp_set_current_user( self::$editor );
		$this->assertTrue( current_user_can( 'edit_post_meta', $post_id, Post_Meta::KEY ) );
	}

	public function test_custom_type_without_custom_fields() {
		register_post_type(
			'atproto_book',
			array(
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'atproto-books',
				'supports'     => array( 'title', 'editor' ),
			)
		);
		$this->assertFalse( post_type_supports( 'atproto_book', 'custom-fields' ) );
		$GLOBALS['wp_rest_server'] = null; // Rebuild routes with the new type.

		update_option( Settings::OPTION, array_merge( Settings::get(), array( 'post_types' => array( 'post', 'atproto_book' ) ) ) );
		Post_Meta::register();

		$this->assertTrue( post_type_supports( 'atproto_book', 'custom-fields' ) );

		wp_set_current_user( self::$editor );
		$post_id  = self::factory()->post->create( array( 'post_type' => 'atproto_book' ) );
		$response = $this->update( "/wp/v2/atproto-books/$post_id", self::URI );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( self::URI, $response->get_data()['meta'][ Post_Meta::KEY ] );
		$this->assertSame( self::URI, get_post_meta( $post_id, Post_Meta::KEY, true ) );

		unregister_post_type( 'atproto_book' );
	}

	public function test_sanitize_callback() {
		$this->assertSame( self::URI, Post_Meta::sanitize( 'https://bsky.app/profile/did:plc:abc234567abcdefghijklmno/post/3mwso7oovnp2u' ) );
		$this->assertSame( 'hola', Post_Meta::sanitize( ' hola ' ) );
		$this->assertSame( '', Post_Meta::sanitize( '  ' ) );
	}
}
