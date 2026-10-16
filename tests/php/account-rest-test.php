<?php
/**
 * Tests for the connection test route, without network.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;

/**
 * POST /atproto-comments/v1/account/test.
 */
class Account_Rest_Test extends WP_UnitTestCase {

	use Atproto_Comments_Network_Mock;

	const DID = 'did:plc:abc234567abcdefghijklmno';

	const PDS = 'https://pds.eurosky.social';

	const PASSWORD = 'abcd-efgh-ijkl-mnop';

	const SESSION_URL = 'https://pds.eurosky.social/xrpc/com.atproto.server.createSession';

	/**
	 * Start every test without an account, as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Account::OPTION );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Saves the account.
	 */
	private function save_account() {
		update_option(
			Account::OPTION,
			array(
				'identifier'   => 'usoritual.com',
				'app_password' => self::PASSWORD,
			)
		);
	}

	/**
	 * Simulates the identity and the PDS session.
	 *
	 * @param array|WP_Error $session createSession response.
	 */
	private function mock_account( $session ) {
		$this->mock_network(
			array(
				'https://plc.directory/' . self::DID => self::response( 200, self::did_document( self::DID, self::PDS, array( 'usoritual.com' ) ) ),
				self::SESSION_URL                    => $session,
			),
			array( '_atproto.usoritual.com' => array( 'did=' . self::DID ) )
		);
	}

	/**
	 * Calls the route.
	 *
	 * @return WP_REST_Response
	 */
	private function dispatch() {
		return rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/atproto-comments/v1/account/test' ) );
	}

	public function test_success() {
		$this->save_account();
		$this->mock_account(
			self::response(
				200,
				array(
					'accessJwt' => 'access',
					'did'       => self::DID,
				)
			)
		);

		$response = $this->dispatch();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'handle' => 'usoritual.com',
				'did'    => self::DID,
				'pds'    => self::PDS,
			),
			$response->get_data()
		);
		$this->assertSame( self::DID, Account::get()['did'] );
		$body = json_decode( $this->request_to( self::SESSION_URL )['args']['body'], true );
		$this->assertSame(
			array(
				'identifier' => self::DID,
				'password'   => self::PASSWORD,
			),
			$body
		);
	}

	public function test_rejected_credentials() {
		$this->save_account();
		$this->mock_account(
			self::response(
				401,
				array(
					'error'   => 'AuthenticationRequired',
					'message' => 'Invalid identifier or password',
				)
			)
		);

		$response = $this->dispatch();

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( 'atproto_comments_auth', $response->get_data()['code'] );
		$this->assertStringNotContainsString( self::PASSWORD, wp_json_encode( $response->get_data() ) );
	}

	public function test_session_for_another_did_fails() {
		$this->save_account();
		$this->mock_account(
			self::response(
				200,
				array(
					'accessJwt' => 'access',
					'did'       => 'did:plc:zzz234567abcdefghijklmno',
				)
			)
		);

		$this->assertSame( 'atproto_comments_server', $this->dispatch()->get_data()['code'] );
	}

	public function test_without_account() {
		$this->mock_network( array() );

		$response = $this->dispatch();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'atproto_comments_account_missing', $response->get_data()['code'] );
		$this->assertSame( array(), $this->requests );
	}

	public function test_editor_is_forbidden() {
		$this->save_account();
		$this->mock_network( array() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( 403, $this->dispatch()->get_status() );
		$this->assertSame( array(), $this->requests );
	}

	public function test_get_is_not_allowed() {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/atproto-comments/v1/account/test' ) );
		$this->assertSame( 404, $response->get_status() );
	}
}
