<?php
/**
 * Tests for publishing the anchor post: record, image, flow and errors.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Anchor_Post;
use AtprotoComments\Display;
use AtprotoComments\Graphemes;
use AtprotoComments\Post_Meta;

/**
 * What is sent to the PDS and what is saved.
 */
class Anchor_Post_Publish_Test extends Atproto_Comments_Publish_Test_Case {

	public function tear_down() {
		Display::reset();
		parent::tear_down();
	}

	/**
	 * Sets the featured image of a post from a test image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $file    File in the WordPress test data.
	 * @return int Attachment ID.
	 */
	private function set_thumbnail( $post_id, $file ) {
		$attachment_id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/' . $file, $post_id );
		set_post_thumbnail( $post_id, $attachment_id );
		return $attachment_id;
	}

	/**
	 * Whether uploadBlob was called.
	 *
	 * @return bool
	 */
	private function uploaded_blob() {
		return null !== $this->request_to( self::BLOB_URL );
	}

	/**
	 * Sum of the timeouts of all requests.
	 *
	 * @return float
	 */
	private function total_timeout() {
		return array_sum(
			array_map(
				static function ( $request ) {
					return (float) $request['args']['timeout'];
				},
				$this->requests
			)
		);
	}

	// Record (design §9).

	public function test_record() {
		$this->mock_pds();
		$post_id = $this->published_post(
			array(
				'post_title'   => 'Café &amp; tostadas',
				'post_excerpt' => '<p>Un <strong>resumen</strong> &amp; más</p>',
			)
		);

		$this->assertSame( 201, $this->publish( $post_id, 'Nueva entrada' )->get_status() );

		$body = json_decode( $this->request_to( self::RECORD_URL )['args']['body'], true );
		$this->assertSame( self::DID, $body['repo'] );
		$this->assertSame( 'app.bsky.feed.post', $body['collection'] );
		$this->assertSame( 'Bearer access', $this->request_to( self::RECORD_URL )['args']['headers']['Authorization'] );

		$record = $body['record'];
		$this->assertSame( 'app.bsky.feed.post', $record['$type'] );
		$this->assertSame( 'Nueva entrada', $record['text'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $record['createdAt'] );
		$this->assertEqualsWithDelta( time(), strtotime( $record['createdAt'] ), 5 );
		$this->assertSame( array( 'en' ), $record['langs'] );
		$this->assertSame(
			array(
				'$type'    => 'app.bsky.embed.external',
				'external' => array(
					'uri'         => get_permalink( $post_id ),
					'title'       => 'Café & tostadas',
					'description' => 'Un resumen & más',
				),
			),
			$record['embed']
		);
	}

	public function test_hashtag_is_plain_text_without_facets() {
		$this->mock_pds();
		$post_id = $this->published_post();

		$this->publish( $post_id, 'Nueva entrada #WordPress https://example.com @usoritual.com' );

		$record = $this->sent_record();
		$this->assertSame( 'Nueva entrada #WordPress https://example.com @usoritual.com', $record['text'] );
		$this->assertArrayNotHasKey( 'facets', $record );
	}

	public function test_password_protected_entry_has_no_description() {
		$this->mock_pds();
		$post_id = $this->published_post(
			array(
				'post_title'    => 'Privada',
				'post_password' => 'secreto',
				'post_excerpt'  => 'Texto que no debe salir',
				'post_content'  => 'Contenido que no debe salir',
			)
		);

		$this->publish( $post_id );

		$external = $this->sent_record()['embed']['external'];
		$this->assertSame( 'Privada', $external['title'] );
		$this->assertSame( '', $external['description'] );
		$this->assertSame( get_permalink( $post_id ), $external['uri'] );
	}

	public function test_long_excerpt_is_cut_to_300_graphemes() {
		$this->mock_pds();
		$post_id = $this->published_post( array( 'post_excerpt' => str_repeat( 'ñ', 280 ) . ' ' . str_repeat( '👩‍💻', 50 ) ) );

		$this->publish( $post_id );

		$description = $this->sent_record()['embed']['external']['description'];
		$this->assertSame( 300, Graphemes::count( $description ) );
		$this->assertStringEndsWith( '👩‍💻', $description );
	}

	/**
	 * Site locales and the expected `langs`.
	 *
	 * @return array
	 */
	public function locales() {
		return array(
			'es_ES'        => array( 'es_ES', array( 'es' ) ),
			'gl_ES'        => array( 'gl_ES', array( 'gl' ) ),
			'pt_BR'        => array( 'pt_BR', array( 'pt' ) ),
			'de_DE_formal' => array( 'de_DE_formal', array( 'de' ) ),
			'fil'          => array( 'fil', array( 'fil' ) ),
		);
	}

	/**
	 * `langs` is the primary subtag of the site locale.
	 *
	 * @dataProvider locales
	 *
	 * @param string $locale   Site locale.
	 * @param array  $expected Expected langs.
	 */
	public function test_langs( $locale, $expected ) {
		$this->mock_pds();
		$post_id = $this->published_post();
		add_filter(
			'locale',
			static function () use ( $locale ) {
				return $locale;
			}
		);

		$this->publish( $post_id );

		$this->assertSame( $expected, $this->sent_record()['langs'] );
	}

	public function test_langs_is_omitted_for_an_odd_locale() {
		$this->mock_pds();
		$post_id = $this->published_post();
		add_filter(
			'locale',
			static function () {
				return 'x-klingon';
			}
		);

		$this->publish( $post_id );

		$this->assertArrayNotHasKey( 'langs', $this->sent_record() );
	}

	// Image (design §10).

	public function test_small_image_is_sent_as_thumb() {
		$this->mock_pds();
		$post_id = $this->published_post();
		$this->set_thumbnail( $post_id, 'canola.jpg' );

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );

		$blob = $this->request_to( self::BLOB_URL );
		$this->assertSame( 'image/jpeg', $blob['args']['headers']['Content-Type'] );
		$this->assertSame( 'Bearer access', $blob['args']['headers']['Authorization'] );
		$this->assertSame( 'bafkreiexample', $this->sent_record()['embed']['external']['thumb']['ref']['$link'] );
	}

	public function test_medium_large_size_is_used() {
		$post_id       = $this->published_post();
		$attachment_id = $this->set_thumbnail( $post_id, '33772.jpg' );
		$size          = image_get_intermediate_size( $attachment_id, 'medium_large' );
		$this->assertIsArray( $size );

		$file = Anchor_Post::thumb_file( get_post( $post_id ) );

		$this->assertSame( wp_basename( $size['file'] ), wp_basename( $file['path'] ) );
		$this->assertSame( 'image/jpeg', $file['mime'] );
	}

	public function test_original_is_used_without_medium_large() {
		$post_id       = $this->published_post();
		$attachment_id = $this->set_thumbnail( $post_id, 'canola.jpg' );
		$this->assertFalse( image_get_intermediate_size( $attachment_id, 'medium_large' ) );

		$this->assertSame( get_attached_file( $attachment_id ), Anchor_Post::thumb_file( get_post( $post_id ) )['path'] );
	}

	public function test_image_of_2_mb_is_not_sent() {
		$this->mock_pds();
		$post_id       = $this->published_post();
		$attachment_id = $this->set_thumbnail( $post_id, '33772.jpg' );
		$size          = image_get_intermediate_size( $attachment_id, 'medium_large' );
		$path          = path_join( dirname( get_attached_file( $attachment_id ) ), wp_basename( $size['file'] ) );
		file_put_contents( $path, file_get_contents( $path ) . str_repeat( "\0", 2000000 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
		$this->assertFalse( $this->uploaded_blob() );
		$this->assertArrayNotHasKey( 'thumb', $this->sent_record()['embed']['external'] );
	}

	public function test_missing_image_file_is_not_sent() {
		$this->mock_pds();
		$post_id       = $this->published_post();
		$attachment_id = $this->set_thumbnail( $post_id, 'canola.jpg' );
		unlink( get_attached_file( $attachment_id ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
		$this->assertFalse( $this->uploaded_blob() );
		$this->assertArrayNotHasKey( 'thumb', $this->sent_record()['embed']['external'] );
	}

	public function test_failed_upload_publishes_without_thumb() {
		$this->mock_pds( array( self::BLOB_URL => self::response( 500, array( 'error' => 'InternalServerError' ) ) ) );
		$post_id = $this->published_post();
		$this->set_thumbnail( $post_id, 'canola.jpg' );

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
		$this->assertTrue( $this->uploaded_blob() );
		$this->assertArrayNotHasKey( 'thumb', $this->sent_record()['embed']['external'] );
	}

	public function test_no_image_without_enough_time() {
		// The DID document takes 15 s: 10 s are left after the session.
		$this->mock_pds(
			array(
				'https://plc.directory/' . self::DID => function () {
					$this->now += 15;
					return self::response( 200, self::did_document( self::DID, 'https://pds.eurosky.social', array( 'usoritual.com' ) ) );
				},
			)
		);
		$post_id = $this->published_post();
		$this->set_thumbnail( $post_id, 'canola.jpg' );

		$this->assertSame( 201, $this->publish( $post_id )->get_status() );
		$this->assertFalse( $this->uploaded_blob() );
		$this->assertArrayNotHasKey( 'thumb', $this->sent_record()['embed']['external'] );
		$this->assertLessThanOrEqual( 25.0, $this->total_timeout() );
		$this->assertSame( 10.0, (float) $this->request_to( self::RECORD_URL )['args']['timeout'] );
	}

	public function test_entry_without_image() {
		$this->mock_pds();
		$post_id = $this->published_post();

		$this->publish( $post_id );

		$this->assertFalse( $this->uploaded_blob() );
		$this->assertArrayNotHasKey( 'thumb', $this->sent_record()['embed']['external'] );
	}

	// Flow and errors.

	public function test_successful_publication() {
		$this->mock_pds();
		$post_id = $this->published_post();

		$response = $this->publish( $post_id );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( array( 'uri' => self::NEW_URI ), $response->get_data() );
		$this->assertSame( self::NEW_URI, get_post_meta( $post_id, Post_Meta::KEY, true ) );

		$session = json_decode( $this->request_to( self::SESSION_URL )['args']['body'], true );
		$this->assertSame( self::DID, $session['identifier'] );

		$this->go_to( get_permalink( $post_id ) );
		$this->assertTrue( Display::should_render( $post_id ) );
	}

	public function test_response_never_contains_the_password() {
		$this->mock_pds();
		$post_id = $this->published_post();

		$this->assertStringNotContainsString( self::PASSWORD, wp_json_encode( $this->publish( $post_id )->get_data() ) );
	}

	/**
	 * PDS failures and the expected error. The link never changes.
	 *
	 * @return array
	 */
	public function failures() {
		return array(
			'rejected credentials' => array( array( self::SESSION_URL => self::response( 401, array( 'error' => 'AuthenticationRequired' ) ) ), 502, 'atproto_comments_auth' ),
			'rate limited session' => array( array( self::SESSION_URL => self::response( 429, array( 'error' => 'RateLimitExceeded' ) ) ), 429, 'atproto_comments_rate_limited' ),
			'rate limited record'  => array( array( self::RECORD_URL => self::response( 429, array( 'error' => 'RateLimitExceeded' ) ) ), 429, 'atproto_comments_rate_limited' ),
			'record timeout'       => array( array( self::RECORD_URL => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) ), 502, 'atproto_comments_uncertain' ),
			'record invalid uri'   => array( array( self::RECORD_URL => self::response( 200, array( 'uri' => 'at://x/y/z' ) ) ), 502, 'atproto_comments_uncertain' ),
			'record server error'  => array( array( self::RECORD_URL => self::response( 400, array( 'error' => 'InvalidRequest' ) ) ), 502, 'atproto_comments_server' ),
			'handle not verified'  => array( array( 'https://plc.directory/' . self::DID => self::response( 200, self::did_document( self::DID, 'https://pds.eurosky.social', array( 'other.example' ) ) ) ), 502, 'atproto_comments_handle_mismatch' ),
		);
	}

	/**
	 * Each failure returns its error and leaves the link unchanged.
	 *
	 * @dataProvider failures
	 *
	 * @param array  $overrides PDS answers.
	 * @param int    $status    Expected status.
	 * @param string $code      Expected code.
	 */
	public function test_failure_keeps_the_link( $overrides, $status, $code ) {
		$this->mock_pds( $overrides );
		$post_id = $this->published_post();

		$response = $this->publish( $post_id );

		$this->assertSame( $status, $response->get_status() );
		$this->assertSame( $code, $response->get_data()['code'] );
		$this->assertNotEmpty( $response->get_data()['message'] );
		$this->assertSame( '', get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}

	public function test_uncertain_message_explains_what_to_do() {
		$this->mock_pds( array( self::RECORD_URL => new WP_Error( 'http_request_failed', 'cURL error 28' ) ) );
		$post_id = $this->published_post();

		$message = $this->publish( $post_id )->get_data()['message'];

		$this->assertStringContainsString( 'profile', $message );
		$this->assertStringContainsString( 'link', $message );
	}

	public function test_no_time_for_record_is_not_uncertain() {
		// The identity takes 20 s and the session 3.5 s: 1.5 s are left, not
		// enough to create the post.
		$this->mock_pds(
			array(
				'https://plc.directory/' . self::DID => function () {
					$this->now += 20;
					return self::response( 200, self::did_document( self::DID, 'https://pds.eurosky.social', array( 'usoritual.com' ) ) );
				},
				self::SESSION_URL                    => function () {
					$this->now += 3.5;
					return self::response(
						200,
						array(
							'accessJwt' => 'access',
							'did'       => self::DID,
						)
					);
				},
			)
		);
		$post_id = $this->published_post();

		$response = $this->publish( $post_id );

		$this->assertSame( 'atproto_comments_timeout', $response->get_data()['code'] );
		$this->assertNotNull( $this->request_to( self::SESSION_URL ) );
		$this->assertNull( $this->request_to( self::RECORD_URL ) );
		$this->assertSame( '', get_post_meta( $post_id, Post_Meta::KEY, true ) );
	}
}
