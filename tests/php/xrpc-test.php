<?php
/**
 * Tests for Xrpc, without network.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Deadline;
use AtprotoComments\Identity;
use AtprotoComments\Xrpc;

/**
 * XRPC requests and the error map.
 */
class Xrpc_Test extends WP_UnitTestCase {

	use Atproto_Comments_Network_Mock;

	const PDS = 'https://pds.eurosky.social';

	const DID = 'did:plc:abc234567abcdefghijklmno';

	const PASSWORD = 'abcd-efgh-ijkl-mnop';

	const SESSION_URL = 'https://pds.eurosky.social/xrpc/com.atproto.server.createSession';

	const RECORD_URL = 'https://pds.eurosky.social/xrpc/com.atproto.repo.createRecord';

	const BLOB_URL = 'https://pds.eurosky.social/xrpc/com.atproto.repo.uploadBlob';

	/**
	 * Deadline with a fixed simulated clock.
	 *
	 * @param float $seconds Seconds left.
	 * @return Deadline
	 */
	private static function deadline( $seconds ) {
		return new Deadline(
			$seconds,
			static function () {
				return 1000.0;
			}
		);
	}

	public function test_procedure_sends_json() {
		$this->mock_network( array( self::RECORD_URL => self::response( 200, array( 'uri' => 'at://x' ) ) ) );

		$result = Xrpc::procedure( self::PDS . '/', 'com.atproto.repo.createRecord', array( 'repo' => self::DID ), 'token-1' );

		$this->assertSame( array( 'uri' => 'at://x' ), $result );
		$args = $this->request_to( self::RECORD_URL )['args'];
		$this->assertSame( 'POST', $args['method'] );
		$this->assertSame( 10, $args['timeout'] );
		$this->assertSame( 0, $args['redirection'] );
		$this->assertTrue( $args['reject_unsafe_urls'] );
		$this->assertSame( 'application/json', $args['headers']['Content-Type'] );
		$this->assertSame( 'Bearer token-1', $args['headers']['Authorization'] );
		$this->assertSame( array( 'repo' => self::DID ), json_decode( $args['body'], true ) );
	}

	public function test_upload_blob_sends_bytes() {
		$blob = array(
			'blob' => array(
				'$type' => 'blob',
				'ref'   => array( '$link' => 'bafkrei' ),
			),
		);
		$this->mock_network( array( self::BLOB_URL => self::response( 200, $blob ) ) );

		$this->assertSame( $blob, Xrpc::upload_blob( self::PDS, "\x89PNG", 'image/png', 'token-1' ) );
		$args = $this->request_to( self::BLOB_URL )['args'];
		$this->assertSame( 20, $args['timeout'] );
		$this->assertSame( 'image/png', $args['headers']['Content-Type'] );
		$this->assertSame( "\x89PNG", $args['body'] );
	}

	public function test_deadline_caps_timeouts() {
		$this->mock_network(
			array(
				self::RECORD_URL => self::response( 200, array() ),
				self::BLOB_URL   => self::response( 200, array() ),
			)
		);

		Xrpc::procedure( self::PDS, 'com.atproto.repo.createRecord', array(), 't', self::deadline( 7 ) );
		Xrpc::upload_blob( self::PDS, 'x', 'image/png', 't', self::deadline( 15 ) );

		$this->assertSame( 7.0, $this->request_to( self::RECORD_URL )['args']['timeout'] );
		$this->assertSame( 15.0, $this->request_to( self::BLOB_URL )['args']['timeout'] );
	}

	public function test_no_request_when_deadline_is_almost_over() {
		$this->mock_network( array( self::RECORD_URL => self::response( 200, array() ) ) );

		$result = Xrpc::procedure( self::PDS, 'com.atproto.repo.createRecord', array(), 't', self::deadline( 1.5 ) );

		$this->assertSame( 'atproto_comments_timeout', $result->get_error_code() );
		$this->assertSame( 502, $result->get_error_data()['status'] );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Rows of the error table in design §6.
	 *
	 * @return array
	 */
	public function error_responses() {
		return array(
			'timeout'                 => array( new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ), 'atproto_comments_network', 502 ),
			'401'                     => array(
				self::response(
					401,
					array(
						'error'   => 'AuthenticationRequired',
						'message' => 'Invalid identifier or password',
					)
				),
				'atproto_comments_auth',
				502,
			),
			'401 without body'        => array( self::response( 401 ), 'atproto_comments_auth', 502 ),
			'InvalidPassword'         => array( self::response( 400, array( 'error' => 'InvalidPassword' ) ), 'atproto_comments_auth', 502 ),
			'AuthFactorTokenRequired' => array(
				self::response(
					401,
					array(
						'error'   => 'AuthFactorTokenRequired',
						'message' => 'A sign in code has been sent',
					)
				),
				'atproto_comments_auth_factor',
				502,
			),
			'AccountTakedown'         => array(
				self::response(
					401,
					array(
						'error'   => 'AccountTakedown',
						'message' => 'Account has been suspended',
					)
				),
				'atproto_comments_account_unavailable',
				502,
			),
			'AccountDeactivated'      => array( self::response( 401, array( 'error' => 'AccountDeactivated' ) ), 'atproto_comments_account_unavailable', 502 ),
			'429'                     => array(
				self::response(
					429,
					array(
						'error'   => 'RateLimitExceeded',
						'message' => 'Rate Limit Exceeded',
					)
				),
				'atproto_comments_rate_limited',
				429,
			),
			'500'                     => array( self::response( 500, array( 'error' => 'InternalServerError' ) ), 'atproto_comments_server', 502 ),
			'invalid JSON'            => array( self::response( 200, '<html>proxy</html>' ), 'atproto_comments_server', 502 ),
			'redirect'                => array( self::response( 302, '' ), 'atproto_comments_server', 502 ),
		);
	}

	/**
	 * Each PDS error becomes its WP_Error, without the PDS message or the
	 * password.
	 *
	 * @dataProvider error_responses
	 *
	 * @param array|WP_Error $response Simulated response.
	 * @param string         $code     Expected code.
	 * @param int            $status   Expected REST status.
	 */
	public function test_error_map( $response, $code, $status ) {
		$this->mock_network( array( self::SESSION_URL => $response ) );

		$result = Xrpc::create_session( self::PDS, self::DID, self::PASSWORD );

		$this->assertWPError( $result );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertSame( $status, $result->get_error_data()['status'] );
		$this->assertNotEmpty( $result->get_error_message() );

		$serialized = serialize( $result ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->assertStringNotContainsString( self::PASSWORD, $serialized );
		foreach ( array( 'Invalid identifier', 'sign in code', 'has been suspended', 'Rate Limit Exceeded', 'cURL', '<html>' ) as $leak ) {
			$this->assertStringNotContainsString( $leak, $serialized );
		}
	}

	public function test_error_name_is_appended_for_support() {
		$this->mock_network( array( self::SESSION_URL => self::response( 400, array( 'error' => 'InvalidRequest' ) ) ) );

		$this->assertStringEndsWith( ' (InvalidRequest)', Xrpc::create_session( self::PDS, self::DID, self::PASSWORD )->get_error_message() );
	}

	public function test_unsafe_error_name_is_not_appended() {
		$this->mock_network( array( self::SESSION_URL => self::response( 400, array( 'error' => '<script>x</script>' ) ) ) );

		$this->assertStringNotContainsString( 'script', Xrpc::create_session( self::PDS, self::DID, self::PASSWORD )->get_error_message() );
	}

	public function test_create_session() {
		$this->mock_network(
			array(
				self::SESSION_URL => self::response(
					200,
					array(
						'accessJwt'  => 'access',
						'refreshJwt' => 'refresh',
						'did'        => self::DID,
						'handle'     => 'usoritual.com',
					)
				),
			)
		);

		$this->assertSame(
			array(
				'access_jwt' => 'access',
				'did'        => self::DID,
			),
			Xrpc::create_session( self::PDS, self::DID, self::PASSWORD )
		);
		$args = $this->request_to( self::SESSION_URL )['args'];
		$this->assertArrayNotHasKey( 'Authorization', $args['headers'] );
	}

	public function test_create_session_with_other_did_fails() {
		$this->mock_network(
			array(
				self::SESSION_URL => self::response(
					200,
					array(
						'accessJwt' => 'access',
						'did'       => 'did:plc:zzz234567abcdefghijklmno',
					)
				),
			)
		);

		$this->assertSame( 'atproto_comments_server', Xrpc::create_session( self::PDS, self::DID, self::PASSWORD )->get_error_code() );
	}

	public function test_session_identifier_is_the_did_for_a_handle_account() {
		$this->mock_network(
			array(
				'https://plc.directory/' . self::DID => self::response( 200, self::did_document( self::DID, self::PDS, array( 'usoritual.com' ) ) ),
				self::SESSION_URL                    => self::response(
					200,
					array(
						'accessJwt' => 'access',
						'did'       => self::DID,
					)
				),
			),
			array( '_atproto.usoritual.com' => array( 'did=' . self::DID ) )
		);

		$account = Identity::resolve_account( 'usoritual.com' );
		$this->assertNotWPError( Xrpc::create_session( $account['pds'], $account['did'], self::PASSWORD ) );

		$body = json_decode( $this->request_to( self::SESSION_URL )['args']['body'], true );
		$this->assertSame( self::DID, $body['identifier'] );
	}
}
