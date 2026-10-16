<?php
/**
 * Base class for the anchor post tests: a configured account and a
 * simulated identity and PDS.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;
use AtprotoComments\Anchor_Post;

/**
 * Account, network and route helpers.
 */
abstract class Atproto_Comments_Publish_Test_Case extends WP_UnitTestCase {

	use Atproto_Comments_Network_Mock;

	const DID = 'did:plc:abc234567abcdefghijklmno';

	const PASSWORD = 'abcd-efgh-ijkl-mnop';

	const SESSION_URL = 'https://pds.eurosky.social/xrpc/com.atproto.server.createSession';

	const BLOB_URL = 'https://pds.eurosky.social/xrpc/com.atproto.repo.uploadBlob';

	const RECORD_URL = 'https://pds.eurosky.social/xrpc/com.atproto.repo.createRecord';

	const NEW_URI = 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3newpost22222';

	/**
	 * Simulated time for the deadline.
	 *
	 * @var float
	 */
	protected $now = 1000.0;

	/**
	 * Configured account, simulated clock and an editor as current user.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Account::OPTION );
		update_option(
			Account::OPTION,
			array(
				'identifier'   => 'usoritual.com',
				'app_password' => self::PASSWORD,
			)
		);
		Anchor_Post::set_clock(
			function () {
				return $this->now;
			}
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Restores the clock.
	 */
	public function tear_down() {
		Anchor_Post::set_clock( null );
		parent::tear_down();
	}

	/**
	 * A published post of another author.
	 *
	 * @param array $args Post fields.
	 * @return int
	 */
	protected function published_post( array $args = array() ) {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_status' => 'publish',
					'post_title'  => 'Entrada',
					'post_author' => self::factory()->user->create( array( 'role' => 'author' ) ),
				),
				$args
			)
		);
	}

	/**
	 * Simulates the identity and the PDS. Each entry of `$overrides` replaces
	 * the answer of one URL.
	 *
	 * @param array $overrides URL => response.
	 */
	protected function mock_pds( array $overrides = array() ) {
		$this->mock_network(
			array_merge(
				array(
					'https://plc.directory/' . self::DID => self::response( 200, self::did_document( self::DID, 'https://pds.eurosky.social', array( 'usoritual.com' ) ) ),
					self::SESSION_URL                    => self::response(
						200,
						array(
							'accessJwt' => 'access',
							'did'       => self::DID,
						)
					),
					self::BLOB_URL                       => self::response(
						200,
						array(
							'blob' => array(
								'$type'    => 'blob',
								'ref'      => array( '$link' => 'bafkreiexample' ),
								'mimeType' => 'image/jpeg',
								'size'     => 1234,
							),
						)
					),
					self::RECORD_URL                     => self::response(
						200,
						array(
							'uri' => self::NEW_URI,
							'cid' => 'bafyreiexample',
						)
					),
				),
				$overrides
			),
			array( '_atproto.usoritual.com' => array( 'did=' . self::DID ) )
		);
	}

	/**
	 * Calls the publish route.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $text    Text.
	 * @return WP_REST_Response
	 */
	protected function publish( $post_id, $text = 'Nueva entrada' ) {
		$request = new WP_REST_Request( 'POST', '/atproto-comments/v1/posts/' . $post_id . '/anchor-post' );
		$request->set_param( 'text', $text );
		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Requests made to the PDS (not to the identity services).
	 *
	 * @return array
	 */
	protected function pds_requests() {
		return array_values(
			array_filter(
				$this->requests,
				static function ( $request ) {
					return 0 === strpos( $request['url'], 'https://pds.eurosky.social/' );
				}
			)
		);
	}

	/**
	 * Record sent to createRecord.
	 *
	 * @return array|null
	 */
	protected function sent_record() {
		$request = $this->request_to( self::RECORD_URL );
		return $request ? json_decode( $request['args']['body'], true )['record'] : null;
	}
}
