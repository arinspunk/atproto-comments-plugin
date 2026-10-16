<?php
/**
 * Tests for the publish route: permission and validation (no PDS calls).
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;
use AtprotoComments\Anchor_Post;
use AtprotoComments\Graphemes;
use AtprotoComments\Post_Meta;
use AtprotoComments\Settings;

/**
 * POST /atproto-comments/v1/posts/<id>/anchor-post before contacting the PDS.
 */
class Anchor_Post_Route_Test extends Atproto_Comments_Publish_Test_Case {

	/**
	 * Asserts an error response without any HTTP request or DNS lookup.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param int              $status   Expected status.
	 * @param string|null      $code     Expected code.
	 */
	private function assertRejectedWithoutNetwork( $response, $status, $code = null ) {
		$this->assertSame( $status, $response->get_status() );
		if ( null !== $code ) {
			$this->assertSame( $code, $response->get_data()['code'] );
		}
		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->dns_lookups );
	}

	public function test_editor_can_publish() {
		$this->mock_pds();
		$post_id = $this->published_post();

		$this->assertTrue( Anchor_Post::user_can_publish( $post_id ) );
		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
	}

	public function test_author_on_own_post_is_forbidden() {
		$this->mock_pds();
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$post_id = $this->published_post( array( 'post_author' => $author ) );

		$this->assertFalse( Anchor_Post::user_can_publish( $post_id ) );
		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 403 );
	}

	public function test_filter_opens_publishing_to_authors() {
		$this->mock_pds();
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$post_id = $this->published_post( array( 'post_author' => $author ) );
		add_filter(
			'atproto_comments_publish_capability',
			static function ( $capability, $post ) use ( $post_id ) {
				return $post->ID === $post_id ? 'publish_posts' : $capability;
			},
			10,
			2
		);

		$this->assertTrue( Anchor_Post::user_can_publish( $post_id ) );
		$response = $this->publish( $post_id );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( self::NEW_URI, get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}

	public function test_filter_can_close_publishing() {
		$this->mock_pds();
		$post_id = $this->published_post();
		add_filter(
			'atproto_comments_publish_capability',
			static function () {
				return 'manage_options';
			}
		);

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 403 );
	}

	public function test_without_edit_post_is_forbidden() {
		$this->mock_pds();
		$post_id = $this->published_post();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 403 );
	}

	public function test_anonymous_is_rejected() {
		$this->mock_pds();
		$post_id = $this->published_post();
		wp_set_current_user( 0 );

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 401 );
	}

	public function test_cookie_auth_without_nonce_is_rejected() {
		$this->mock_pds();
		$post_id = $this->published_post();

		// What rest_cookie_check_errors() does for a cookie session without
		// X-WP-Nonce: the request continues as anonymous.
		$GLOBALS['wp_rest_auth_cookie'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global set by wp_validate_auth_cookie().
		unset( $_REQUEST['_wpnonce'], $_SERVER['HTTP_X_WP_NONCE'] );
		$this->assertTrue( rest_cookie_check_errors( null ) );
		unset( $GLOBALS['wp_rest_auth_cookie'] );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 401 );
		$this->assertSame( '', get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}

	public function test_missing_post() {
		$this->mock_pds();

		$this->assertRejectedWithoutNetwork( $this->publish( 999999 ), 404, 'atproto_comments_post_not_found' );
	}

	public function test_draft() {
		$this->mock_pds();
		$post_id = $this->published_post( array( 'post_status' => 'draft' ) );

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 400, 'atproto_comments_not_published' );
	}

	public function test_inactive_type() {
		$this->mock_pds();
		$post_id = $this->published_post( array( 'post_type' => 'page' ) );
		$this->assertFalse( Settings::is_active_type( 'page' ) );

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 400, 'atproto_comments_inactive_type' );
	}

	public function test_already_linked() {
		$this->mock_pds();
		$post_id = $this->published_post();
		update_post_meta( $post_id, Post_Meta::KEY, 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u' );

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 409, 'atproto_comments_already_linked' );
	}

	public function test_account_not_configured() {
		$this->mock_pds();
		delete_option( Account::OPTION );
		$post_id = $this->published_post();

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 400, 'atproto_comments_account_missing' );
	}

	public function test_account_with_unreadable_password() {
		$this->mock_pds();
		$post_id = $this->published_post();
		add_filter(
			'salt',
			static function ( $salt ) {
				return $salt . 'rotated';
			}
		);

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id ), 400, 'atproto_comments_account_missing' );
	}

	/**
	 * Texts that are rejected.
	 *
	 * @return array
	 */
	public function invalid_texts() {
		return array(
			'empty'                 => array( '', 'atproto_comments_text_empty' ),
			'only spaces'           => array( "  \n ", 'atproto_comments_text_empty' ),
			'invalid UTF-8'         => array( "caf\xC3", 'atproto_comments_text_empty' ),
			'301 graphemes'         => array( str_repeat( 'a', 301 ), 'atproto_comments_text_too_long' ),
			'300 graphemes, 7500 B' => array( str_repeat( '👨‍👩‍👧‍👦', 300 ), 'atproto_comments_text_too_long' ),
			'301 consecutive emoji' => array( str_repeat( '😀', 301 ), 'atproto_comments_text_too_long' ),
		);
	}

	/**
	 * Invalid texts are rejected without contacting the PDS.
	 *
	 * @dataProvider invalid_texts
	 *
	 * @param string $text Text.
	 * @param string $code Expected code.
	 */
	public function test_invalid_text( $text, $code ) {
		$this->mock_pds();
		$post_id = $this->published_post();

		$this->assertRejectedWithoutNetwork( $this->publish( $post_id, $text ), 400, $code );
	}

	public function test_missing_text_parameter() {
		$this->mock_pds();
		$post_id  = $this->published_post();
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/atproto-comments/v1/posts/' . $post_id . '/anchor-post' ) );

		$this->assertRejectedWithoutNetwork( $response, 400 );
	}

	public function test_300_graphemes_with_zwj_emoji_is_accepted() {
		$this->mock_pds();
		$post_id = $this->published_post();
		$text    = str_repeat( 'a', 299 ) . '👩‍💻';

		$this->assertSame( 300, Graphemes::count( $text ) );
		$this->assertSame( 301, Graphemes::count( $text . 'b' ) );
		$this->assertSame( 201, $this->publish( $post_id, $text )->get_status() );
		$this->assertSame( $text, $this->sent_record()['text'] );
	}

	public function test_flags_count_as_one_grapheme() {
		$this->assertSame( 2, Graphemes::count( '🇪🇸🇬🇧' ) );
		$this->assertSame( 1, Graphemes::count( 'é' ) );
	}

	public function test_publishing_the_entry_does_not_contact_the_pds() {
		$this->mock_pds();

		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		wp_publish_post( $post_id );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated',
			)
		);

		$this->assertSame( array(), $this->requests );
		$this->assertSame( '', get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}
}
