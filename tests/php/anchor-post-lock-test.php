<?php
/**
 * Tests for the double-submit lock of the publish route.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Anchor_Post;
use AtprotoComments\Post_Meta;

/**
 * INSERT IGNORE lock in wp_options.
 */
class Anchor_Post_Lock_Test extends Atproto_Comments_Publish_Test_Case {

	/**
	 * Lock row of a post, or null.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	private function lock_row( $post_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", 'atproto_comments_lock_' . $post_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Inserts a lock row with a given age.
	 *
	 * @param int $post_id Post ID.
	 * @param int $age     Seconds since it was taken.
	 */
	private function insert_lock( $post_id, $age ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->options,
			array(
				'option_name'  => 'atproto_comments_lock_' . $post_id,
				'option_value' => (string) ( time() - $age ),
				'autoload'     => 'no',
			)
		);
	}

	public function test_acquire_and_release() {
		$this->assertTrue( Anchor_Post::acquire_lock( 7 ) );
		$this->assertSame( 'no', $this->lock_row( 7 )['autoload'] );
		$this->assertFalse( Anchor_Post::acquire_lock( 7 ) );
		$this->assertTrue( Anchor_Post::acquire_lock( 8 ) );

		Anchor_Post::release_lock( 7 );
		$this->assertNull( $this->lock_row( 7 ) );
		$this->assertTrue( Anchor_Post::acquire_lock( 7 ) );
	}

	public function test_held_lock_returns_409_without_contacting_the_pds() {
		$this->mock_pds();
		$post_id = $this->published_post();
		$this->insert_lock( $post_id, 5 );

		$response = $this->publish( $post_id );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'atproto_comments_locked', $response->get_data()['code'] );
		$this->assertSame( array(), $this->requests );
		$this->assertNotNull( $this->lock_row( $post_id ), 'Another request\'s lock is not released.' );
	}

	public function test_stale_lock_is_taken_over() {
		$this->mock_pds();
		$post_id = $this->published_post();
		$this->insert_lock( $post_id, 200 );

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
		$this->assertNull( $this->lock_row( $post_id ) );
	}

	public function test_lock_is_released_after_success() {
		$this->mock_pds();
		$post_id = $this->published_post();

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
		$this->assertNull( $this->lock_row( $post_id ) );
	}

	public function test_lock_is_released_when_the_pds_fails() {
		$this->mock_pds( array( self::SESSION_URL => self::response( 401, array( 'error' => 'AuthenticationRequired' ) ) ) );
		$post_id = $this->published_post();

		$this->assertSame( 502, $this->publish( $post_id )->get_status() );
		$this->assertNull( $this->lock_row( $post_id ) );
	}

	public function test_link_is_checked_again_inside_the_lock() {
		$this->mock_pds();
		$post_id = $this->published_post();

		// Another request links the entry right after this one passed the
		// first check, while it takes the lock.
		$linked = false;
		add_filter(
			'query',
			static function ( $query ) use ( $post_id, &$linked ) {
				if ( ! $linked && 0 === strpos( $query, 'INSERT IGNORE' ) ) {
					$linked = true;
					update_post_meta( $post_id, Post_Meta::KEY, 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/other' );
				}
				return $query;
			}
		);

		$response = $this->publish( $post_id );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'atproto_comments_already_linked', $response->get_data()['code'] );
		$this->assertSame( array(), $this->requests );
		$this->assertNull( $this->lock_row( $post_id ) );
	}

	public function test_second_quick_request_does_not_publish_again() {
		$this->mock_pds();
		$post_id = $this->published_post();

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
		$this->assertSame( 409, $this->publish( $post_id )->get_status() );
		$this->assertCount(
			1,
			array_filter(
				wp_list_pluck( $this->requests, 'url' ),
				static function ( $url ) {
					return self::RECORD_URL === $url;
				}
			)
		);
	}
}
