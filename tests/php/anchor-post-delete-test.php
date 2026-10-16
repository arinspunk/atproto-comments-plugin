<?php
/**
 * Tests for deleting the anchor post (DELETE on the anchor post route).
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;
use AtprotoComments\Anchor_Post;
use AtprotoComments\Display;
use AtprotoComments\Post_Meta;
use AtprotoComments\Settings;

/**
 * DELETE /atproto-comments/v1/posts/<id>/anchor-post.
 */
class Anchor_Post_Delete_Test extends Atproto_Comments_Publish_Test_Case {

	const RKEY = '3oldpost22222';

	const LINK = 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3oldpost22222';

	const OTHER_LINK = 'at://did:plc:zzz234567abcdefghijklmno/app.bsky.feed.post/3otherpost222';

	const DELETE_URL = 'https://pds.eurosky.social/xrpc/com.atproto.repo.deleteRecord';

	const GET_RECORD_URL = 'https://pds.eurosky.social/xrpc/com.atproto.repo.getRecord?repo=did%3Aplc%3Aabc234567abcdefghijklmno&collection=app.bsky.feed.post&rkey=3oldpost22222';

	/**
	 * A published post linked to the given URI.
	 *
	 * @param string $uri  Link.
	 * @param array  $args Post fields.
	 * @return int
	 */
	private function linked_post( $uri = self::LINK, array $args = array() ) {
		$post_id = $this->published_post( $args );
		update_post_meta( $post_id, Post_Meta::KEY, $uri );
		return $post_id;
	}

	/**
	 * Simulates the identity and the PDS, with deleteRecord answering 200.
	 *
	 * @param array $overrides URL => response.
	 */
	private function mock_delete( array $overrides = array() ) {
		$this->mock_pds( array_merge( array( self::DELETE_URL => self::response( 200, array() ) ), $overrides ) );
	}

	/**
	 * Calls the DELETE endpoint.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $params  Extra parameters.
	 * @return WP_REST_Response
	 */
	private function delete( $post_id, array $params = array() ) {
		$request = new WP_REST_Request( 'DELETE', '/atproto-comments/v1/posts/' . $post_id . '/anchor-post' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

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

	/**
	 * Asserts that the entry is still linked to LINK.
	 *
	 * @param int $post_id Post ID.
	 */
	private function assertStillLinked( $post_id ) {
		wp_cache_delete( $post_id, 'post_meta' );
		$this->assertSame( self::LINK, get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}

	/**
	 * Asserts that the meta row of the link was deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	private function assertUnlinked( $post_id ) {
		wp_cache_delete( $post_id, 'post_meta' );
		$this->assertFalse( metadata_exists( 'post', $post_id, Post_Meta::KEY ) );
	}

	/**
	 * Takes the lock of a post as another request would.
	 *
	 * @param int $post_id Post ID.
	 */
	private function insert_lock( $post_id ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->options,
			array(
				'option_name'  => 'atproto_comments_lock_' . $post_id,
				'option_value' => (string) time(),
				'autoload'     => 'no',
			)
		);
	}

	// Permission (2.1).

	public function test_delete_does_not_require_text() {
		$this->mock_delete();
		$post_id = $this->linked_post();

		$this->assertTrue( Anchor_Post::user_can_delete( $post_id ) );
		$this->assertSame( 200, $this->delete( $post_id )->get_status() );
	}

	public function test_missing_post() {
		$this->mock_delete();

		$this->assertRejectedWithoutNetwork( $this->delete( 999999 ), 404, 'atproto_comments_post_not_found' );
	}

	public function test_without_delete_post_is_forbidden() {
		$this->mock_delete();
		$post_id = $this->linked_post();
		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap ) {
				return 'delete_post' === $cap ? array( 'do_not_allow' ) : $caps;
			},
			10,
			2
		);

		$this->assertTrue( Anchor_Post::user_can_publish( $post_id ) );
		$this->assertFalse( Anchor_Post::user_can_delete( $post_id ) );
		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 403 );
		$this->assertStillLinked( $post_id );
	}

	public function test_author_on_own_post_is_forbidden() {
		$this->mock_delete();
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$post_id = $this->linked_post( self::LINK, array( 'post_author' => $author ) );

		$this->assertTrue( current_user_can( 'delete_post', $post_id ) );
		$this->assertFalse( Anchor_Post::user_can_delete( $post_id ) );
		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 403 );
		$this->assertStillLinked( $post_id );
	}

	public function test_filter_opens_deleting_to_authors() {
		$this->mock_delete();
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$post_id = $this->linked_post( self::LINK, array( 'post_author' => $author ) );
		add_filter(
			'atproto_comments_publish_capability',
			static function () {
				return 'publish_posts';
			}
		);

		$this->assertSame( 200, $this->delete( $post_id )->get_status() );
		$this->assertUnlinked( $post_id );
	}

	public function test_cookie_auth_without_nonce_is_rejected() {
		$this->mock_delete();
		$post_id = $this->linked_post();

		$GLOBALS['wp_rest_auth_cookie'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global set by wp_validate_auth_cookie().
		unset( $_REQUEST['_wpnonce'], $_SERVER['HTTP_X_WP_NONCE'] );
		$this->assertTrue( rest_cookie_check_errors( null ) );
		unset( $GLOBALS['wp_rest_auth_cookie'] );

		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 401 );
		$this->assertStillLinked( $post_id );
	}

	public function test_method_override_reaches_the_deletion() {
		$this->mock_delete();
		$post_id = $this->linked_post();

		// What apiFetch sends by default: POST with the override header. The
		// header is read by serve_request(), not by dispatch().
		add_filter(
			'wp_rest_server_class',
			static function () {
				return 'Spy_REST_Server';
			}
		);
		$GLOBALS['wp_rest_server']              = null;
		$server                                 = rest_get_server();
		$_SERVER['REQUEST_METHOD']              = 'POST';
		$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'DELETE';
		try {
			$server->serve_request( '/atproto-comments/v1/posts/' . $post_id . '/anchor-post' );
		} finally {
			unset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] );
			$GLOBALS['wp_rest_server'] = null;
		}

		$this->assertSame( 'DELETE', $server->last_request->get_method() );
		$this->assertSame( 200, $server->status );
		$this->assertUnlinked( $post_id );
	}

	// Validation without network and lock (2.2).

	public function test_inactive_type() {
		$this->mock_delete();
		$post_id = $this->linked_post( self::LINK, array( 'post_type' => 'page' ) );

		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 400, 'atproto_comments_inactive_type' );
		$this->assertSame( array( 'post' ), Settings::get()['post_types'] );
	}

	/**
	 * Saved values that are not a link to delete.
	 *
	 * @return array
	 */
	public function not_linked() {
		return array(
			'no meta'      => array( null ),
			'empty string' => array( '' ),
			'not an uri'   => array( 'https://bsky.app/profile/x/post/y' ),
		);
	}

	/**
	 * An entry without a canonical link is rejected.
	 *
	 * @dataProvider not_linked
	 *
	 * @param string|null $value Saved meta, or null for none.
	 */
	public function test_entry_without_link( $value ) {
		$this->mock_delete();
		$post_id = $this->published_post();
		if ( null !== $value ) {
			update_post_meta( $post_id, Post_Meta::KEY, $value );
		}

		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 400, 'atproto_comments_not_linked' );
	}

	public function test_account_not_configured() {
		$this->mock_delete();
		$post_id = $this->linked_post();
		delete_option( Account::OPTION );

		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 400, 'atproto_comments_account_missing' );
		$this->assertStillLinked( $post_id );
	}

	public function test_account_with_unreadable_password() {
		$this->mock_delete();
		$post_id = $this->linked_post();
		add_filter(
			'salt',
			static function ( $salt ) {
				return $salt . 'rotated';
			}
		);

		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 400, 'atproto_comments_account_missing' );
	}

	public function test_held_lock_returns_the_common_409() {
		$this->mock_delete();
		$post_id = $this->linked_post();
		$this->insert_lock( $post_id );

		$response = $this->delete( $post_id );
		$this->assertRejectedWithoutNetwork( $response, 409, 'atproto_comments_locked' );
		$this->assertStringContainsString( 'already an operation in progress', $response->get_data()['message'] );
		$this->assertStillLinked( $post_id );
	}

	public function test_publishing_with_held_lock_returns_the_same_409() {
		$this->mock_pds();
		$post_id = $this->published_post();
		$this->insert_lock( $post_id );

		$response = $this->publish( $post_id );
		$this->assertRejectedWithoutNetwork( $response, 409, 'atproto_comments_locked' );
		$this->assertStringContainsString( 'already an operation in progress', $response->get_data()['message'] );
	}

	public function test_lock_is_released_when_the_pds_fails() {
		$this->mock_delete( array( self::DELETE_URL => self::response( 401, array( 'error' => 'AuthenticationRequired' ) ) ) );
		$post_id = $this->linked_post();

		$this->assertSame( 502, $this->delete( $post_id )->get_status() );
		$this->assertTrue( Anchor_Post::acquire_lock( $post_id ) );
	}

	public function test_link_removed_while_waiting_for_the_lock() {
		$post_id = $this->linked_post();
		$this->mock_delete();
		// Another request unlinks the entry right after validation.
		add_filter(
			'query',
			static function ( $query ) use ( $post_id ) {
				if ( false !== strpos( $query, 'INSERT IGNORE' ) && false !== strpos( $query, 'atproto_comments_lock_' . $post_id ) ) {
					delete_post_meta( $post_id, Post_Meta::KEY );
				}
				return $query;
			}
		);

		$this->assertRejectedWithoutNetwork( $this->delete( $post_id ), 400, 'atproto_comments_not_linked' );
	}

	public function test_extra_parameters_are_ignored() {
		$this->mock_delete();
		$post_id = $this->linked_post();

		$response = $this->delete(
			$post_id,
			array(
				'uri'  => 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3another22222',
				'rkey' => '3another22222',
				'repo' => 'did:plc:zzz234567abcdefghijklmno',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$body = json_decode( $this->request_to( self::DELETE_URL )['args']['body'], true );
		$this->assertSame( self::RKEY, $body['rkey'] );
		$this->assertSame( self::DID, $body['repo'] );
	}

	// Deletion flow (2.3).

	public function test_successful_deletion() {
		$this->mock_delete();
		$post_id = $this->linked_post();

		$response = $this->delete( $post_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'uri' => '' ), $response->get_data() );
		$this->assertSame(
			array(
				'repo'       => self::DID,
				'collection' => 'app.bsky.feed.post',
				'rkey'       => self::RKEY,
			),
			json_decode( $this->request_to( self::DELETE_URL )['args']['body'], true )
		);
		$this->assertSame( 'Bearer access', $this->request_to( self::DELETE_URL )['args']['headers']['Authorization'] );
		$this->assertSame( 'POST', $this->request_to( self::DELETE_URL )['args']['method'] );
		$this->assertUnlinked( $post_id );
		$this->assertFalse( Display::should_render( get_post( $post_id ) ) );
		$this->assertSame( self::DID, Account::get()['did'] );
	}

	public function test_post_of_another_account_is_not_deleted() {
		$this->mock_delete();
		$post_id = $this->linked_post( self::OTHER_LINK );

		$response = $this->delete( $post_id );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'atproto_comments_not_owner', $response->get_data()['code'] );
		$this->assertNull( $this->request_to( self::SESSION_URL ) );
		$this->assertNull( $this->request_to( self::DELETE_URL ) );
		foreach ( $this->requests as $request ) {
			$this->assertStringNotContainsString( self::PASSWORD, (string) ( $request['args']['body'] ?? '' ) );
		}
		wp_cache_delete( $post_id, 'post_meta' );
		$this->assertSame( self::OTHER_LINK, get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}

	public function test_no_request_can_end_after_25_seconds() {
		$post_id = $this->linked_post();
		$slow    = function ( $body ) {
			return function () use ( $body ) {
				$this->now += 8.0;
				return $body;
			};
		};
		$this->mock_delete(
			array(
				'https://usoritual.com/.well-known/atproto-did' => $slow( self::response( 200, self::DID ) ),
				'https://plc.directory/' . self::DID => $slow( self::response( 200, self::did_document( self::DID, 'https://pds.eurosky.social', array( 'usoritual.com' ) ) ) ),
				self::DELETE_URL                     => $slow( new WP_Error( 'http_request_failed', 'cURL error 28' ) ),
			)
		);

		$response = $this->delete( $post_id );

		$this->assertSame( 502, $response->get_status() );
		foreach ( $this->requests as $request ) {
			$this->assertLessThanOrEqual( 25.0, $request['at'] - 1000.0 + (float) $request['args']['timeout'] );
		}
		$this->assertStillLinked( $post_id );
	}

	// Results (2.4).

	/**
	 * deleteRecord answers that mean the post no longer exists.
	 *
	 * @return array
	 */
	public function gone() {
		return array(
			'200'                => array( self::response( 200, array() ) ),
			'RecordNotFound 400' => array( self::response( 400, array( 'error' => 'RecordNotFound' ) ) ),
			'RecordNotFound 404' => array( self::response( 404, array( 'error' => 'RecordNotFound' ) ) ),
		);
	}

	/**
	 * The entry is unlinked without asking getRecord.
	 *
	 * @dataProvider gone
	 *
	 * @param array $answer deleteRecord response.
	 */
	public function test_post_gone_unlinks( $answer ) {
		$this->mock_delete( array( self::DELETE_URL => $answer ) );
		$post_id = $this->linked_post();

		$this->assertSame( 200, $this->delete( $post_id )->get_status() );
		$this->assertUnlinked( $post_id );
		$this->assertNull( $this->request_to( self::GET_RECORD_URL ) );
	}

	/**
	 * Inconclusive deleteRecord answers, confirmed with getRecord.
	 *
	 * @return array
	 */
	public function confirmations() {
		$timeout = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		$html    = self::response( 404, '<html><body>Not Found</body></html>' );
		$method  = self::response( 404, array( 'error' => 'MethodNotImplemented' ) );
		$missing = self::response( 400, array( 'error' => 'RecordNotFound' ) );
		$present = self::response(
			200,
			array(
				'uri'   => self::LINK,
				'cid'   => 'bafyreiexample',
				'value' => array( 'text' => 'Nueva entrada' ),
			)
		);
		$down    = new WP_Error( 'http_request_failed', 'cURL error 7' );

		return array(
			'timeout, record gone'       => array( $timeout, $missing, 200, null ),
			'timeout, record present'    => array( $timeout, $present, 502, 'atproto_comments_delete_failed' ),
			'timeout, no answer'         => array( $timeout, $down, 502, 'atproto_comments_delete_unconfirmed' ),
			'html 404, record gone'      => array( $html, $missing, 200, null ),
			'html 404, record present'   => array( $html, $present, 502, 'atproto_comments_delete_failed' ),
			'html 404, getRecord 500'    => array( $html, self::response( 500, 'oops' ), 502, 'atproto_comments_delete_unconfirmed' ),
			'method 404, record gone'    => array( $method, $missing, 200, null ),
			'method 404, record present' => array( $method, $present, 502, 'atproto_comments_delete_failed' ),
		);
	}

	/**
	 * Inconclusive answers unlink only when getRecord confirms it.
	 *
	 * @dataProvider confirmations
	 *
	 * @param array|WP_Error $delete deleteRecord response.
	 * @param array|WP_Error $get    getRecord response.
	 * @param int            $status Expected status.
	 * @param string|null    $code   Expected error code.
	 */
	public function test_inconclusive_answer_is_confirmed( $delete, $get, $status, $code ) {
		$this->mock_delete(
			array(
				self::DELETE_URL     => $delete,
				self::GET_RECORD_URL => $get,
			)
		);
		$post_id = $this->linked_post();

		$response = $this->delete( $post_id );

		$this->assertSame( $status, $response->get_status() );
		$this->assertSame( 'GET', $this->request_to( self::GET_RECORD_URL )['args']['method'] );
		$this->assertArrayNotHasKey( 'Authorization', $this->request_to( self::GET_RECORD_URL )['args']['headers'] );
		if ( null === $code ) {
			$this->assertUnlinked( $post_id );
		} else {
			$this->assertSame( $code, $response->get_data()['code'] );
			$this->assertStillLinked( $post_id );
		}
	}

	public function test_no_confirmation_without_time() {
		$post_id = $this->linked_post();
		$this->mock_delete(
			array(
				self::DELETE_URL     => function () {
					$this->now += 23.0;
					return new WP_Error( 'http_request_failed', 'cURL error 28' );
				},
				self::GET_RECORD_URL => self::response( 400, array( 'error' => 'RecordNotFound' ) ),
			)
		);

		$response = $this->delete( $post_id );

		$this->assertSame( 'atproto_comments_delete_unconfirmed', $response->get_data()['code'] );
		$this->assertNull( $this->request_to( self::GET_RECORD_URL ) );
		$this->assertStillLinked( $post_id );
	}

	/**
	 * deleteRecord errors that keep the link without asking getRecord.
	 *
	 * @return array
	 */
	public function errors() {
		return array(
			'other 400'   => array( self::response( 400, array( 'error' => 'InvalidRequest' ) ), 502, 'atproto_comments_server' ),
			'credentials' => array( self::response( 401, array( 'error' => 'AuthenticationRequired' ) ), 502, 'atproto_comments_auth' ),
			'rate limit'  => array( self::response( 429, array( 'error' => 'RateLimitExceeded' ) ), 429, 'atproto_comments_rate_limited' ),
			'takedown'    => array( self::response( 400, array( 'error' => 'AccountTakedown' ) ), 502, 'atproto_comments_account_unavailable' ),
			'server 500'  => array( self::response( 500, 'oops' ), 502, 'atproto_comments_server' ),
		);
	}

	/**
	 * Errors keep the link, with their message and without the password.
	 *
	 * @dataProvider errors
	 *
	 * @param array  $answer deleteRecord response.
	 * @param int    $status Expected status.
	 * @param string $code   Expected code.
	 */
	public function test_errors_keep_the_link( $answer, $status, $code ) {
		$this->mock_delete( array( self::DELETE_URL => $answer ) );
		$post_id = $this->linked_post();

		$response = $this->delete( $post_id );

		$this->assertSame( $status, $response->get_status() );
		$this->assertSame( $code, $response->get_data()['code'] );
		$this->assertStringNotContainsString( self::PASSWORD, wp_json_encode( $response->get_data() ) );
		$this->assertNull( $this->request_to( self::GET_RECORD_URL ) );
		$this->assertStillLinked( $post_id );
	}

	public function test_rejected_session_keeps_the_link() {
		$this->mock_delete( array( self::SESSION_URL => self::response( 401, array( 'error' => 'AuthenticationRequired' ) ) ) );
		$post_id = $this->linked_post();

		$response = $this->delete( $post_id );

		$this->assertSame( 'atproto_comments_auth', $response->get_data()['code'] );
		$this->assertStringContainsString( 'Check the account in the settings', $response->get_data()['message'] );
		$this->assertStringNotContainsString( self::PASSWORD, wp_json_encode( $response->get_data() ) );
		$this->assertNull( $this->request_to( self::DELETE_URL ) );
		$this->assertStillLinked( $post_id );
	}

	public function test_retry_after_unconfirmed_deletion_unlinks() {
		$post_id = $this->linked_post();
		$this->mock_delete(
			array(
				self::DELETE_URL     => new WP_Error( 'http_request_failed', 'cURL error 28' ),
				self::GET_RECORD_URL => new WP_Error( 'http_request_failed', 'cURL error 28' ),
			)
		);
		$this->assertSame( 'atproto_comments_delete_unconfirmed', $this->delete( $post_id )->get_data()['code'] );
		$this->assertStillLinked( $post_id );

		// The first attempt did delete the post.
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'atproto_comments_pre_dns_txt' );
		$this->mock_delete( array( self::DELETE_URL => self::response( 400, array( 'error' => 'RecordNotFound' ) ) ) );

		$response = $this->delete( $post_id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertUnlinked( $post_id );
	}

	// WordPress entry and classic editor (2.5).

	public function test_trashing_restoring_and_deleting_the_entry_makes_no_requests() {
		$this->mock_delete();
		$post_id = $this->linked_post();

		wp_trash_post( $post_id );
		wp_untrash_post( $post_id );
		wp_delete_post( $post_id, true );

		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->dns_lookups );
	}

	public function test_classic_metabox_offers_no_deletion() {
		$post_id = $this->linked_post();

		ob_start();
		Post_Meta::render_metabox( get_post( $post_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( self::LINK, $html );
		$this->assertStringNotContainsStringIgnoringCase( 'delete', $html );
		$this->assertStringNotContainsString( 'anchor-post', $html );
	}
}
