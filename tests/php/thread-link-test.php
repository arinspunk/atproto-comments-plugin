<?php
/**
 * Tests for Thread_Link and the resolve-handle route, without network.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Thread_Link;

/**
 * Handle resolution in the thread link.
 */
class Thread_Link_Test extends WP_UnitTestCase {

	use Atproto_Comments_Network_Mock;

	const DID = 'did:plc:abc234567abcdefghijklmno';

	const URI = 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u';

	/**
	 * Transient name for a handle.
	 *
	 * @param string $handle Handle.
	 * @return string
	 */
	private static function cache_key( $handle ) {
		return 'atproto_comments_handle_' . md5( $handle );
	}

	/**
	 * Simulates a handle that resolves by DNS.
	 *
	 * @param string $handle Handle.
	 */
	private function mock_handle( $handle ) {
		$this->mock_network( array(), array( '_atproto.' . $handle => array( 'did=' . self::DID ) ) );
	}

	public function test_app_url_with_handle() {
		$this->mock_handle( 'usoritual.com' );

		$this->assertSame(
			array(
				'ok'  => true,
				'uri' => self::URI,
			),
			Thread_Link::resolve( 'https://bsky.app/profile/UsoRitual.com/post/3mwso7oovnp2u' )
		);
	}

	public function test_at_uri_with_handle() {
		$this->mock_handle( 'usoritual.com' );

		$this->assertSame( self::URI, Thread_Link::resolve( 'at://usoritual.com/app.bsky.feed.post/3mwso7oovnp2u' )['uri'] );
	}

	public function test_did_link_makes_no_request() {
		$this->mock_network( array() );

		$this->assertSame( self::URI, Thread_Link::resolve( 'https://bsky.app/profile/' . self::DID . '/post/3mwso7oovnp2u' )['uri'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->dns_lookups );
	}

	public function test_format_error_is_kept() {
		$this->mock_network( array() );

		$this->assertSame(
			array(
				'ok'    => false,
				'error' => 'format',
			),
			Thread_Link::resolve( 'at://usoritual.com/app.bsky.feed.like/3mwso7oovnp2u' )
		);
	}

	public function test_unresolved_handle_is_not_cached() {
		$this->mock_network( array() );

		$this->assertSame(
			array(
				'ok'    => false,
				'error' => 'handle_unresolved',
			),
			Thread_Link::resolve( 'at://no-existe.example/app.bsky.feed.post/3mwso7oovnp2u' )
		);
		$this->assertFalse( get_transient( self::cache_key( 'no-existe.example' ) ) );
	}

	public function test_second_resolution_uses_cache() {
		$this->mock_handle( 'usoritual.com' );

		Thread_Link::resolve( 'at://usoritual.com/app.bsky.feed.post/3mwso7oovnp2u' );
		$this->assertSame( self::DID, get_transient( self::cache_key( 'usoritual.com' ) ) );
		$this->assertCount( 1, $this->dns_lookups );

		$this->assertSame( self::URI, Thread_Link::resolve( 'https://bsky.app/profile/usoritual.com/post/3mwso7oovnp2u' )['uri'] );
		$this->assertCount( 1, $this->dns_lookups );
	}

	public function test_cache_expires_after_ten_minutes() {
		$this->mock_handle( 'usoritual.com' );
		Thread_Link::resolve_handle( 'usoritual.com' );

		$timeout = (int) get_option( '_transient_timeout_' . self::cache_key( 'usoritual.com' ) );
		$this->assertEqualsWithDelta( time() + 600, $timeout, 5 );
	}

	public function test_handle_of_253_characters() {
		$handle = implode( '.', array( str_repeat( 'a', 63 ), str_repeat( 'b', 63 ), str_repeat( 'c', 63 ), str_repeat( 'd', 61 ) ) );
		$this->assertSame( 253, strlen( $handle ) );
		$this->assertSame( 56, strlen( self::cache_key( $handle ) ) );
		$this->mock_handle( $handle );

		$this->assertSame( self::URI, Thread_Link::resolve( 'at://' . $handle . '/app.bsky.feed.post/3mwso7oovnp2u' )['uri'] );
		$this->assertSame( self::DID, get_transient( self::cache_key( $handle ) ) );
	}

	/**
	 * Calls the resolve-handle route.
	 *
	 * @param string $handle Handle.
	 * @return WP_REST_Response
	 */
	private function dispatch( $handle ) {
		$request = new WP_REST_Request( 'GET', '/atproto-comments/v1/resolve-handle' );
		$request->set_param( 'handle', $handle );
		return rest_get_server()->dispatch( $request );
	}

	public function test_route_resolves() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->mock_handle( 'usoritual.com' );

		$response = $this->dispatch( 'UsoRitual.com' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'did' => self::DID ), $response->get_data() );
	}

	public function test_route_unresolved() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->mock_network( array() );

		$response = $this->dispatch( 'no-existe.example' );

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( 'atproto_comments_handle_unresolved', $response->get_data()['code'] );
	}

	public function test_route_rejects_invalid_handle() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->mock_network( array() );

		$this->assertSame( 400, $this->dispatch( 'no es un handle' )->get_status() );
		$this->assertSame( array(), $this->dns_lookups );
	}

	public function test_route_requires_edit_posts() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->mock_network( array() );

		$this->assertSame( 403, $this->dispatch( 'usoritual.com' )->get_status() );
		$this->assertSame( array(), $this->dns_lookups );
	}
}
