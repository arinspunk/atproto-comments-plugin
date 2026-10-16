<?php
/**
 * Tests for Identity, without network.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Deadline;
use AtprotoComments\Identity;

/**
 * Handle, DID and account resolution.
 */
class Identity_Test extends WP_UnitTestCase {

	use Atproto_Comments_Network_Mock;

	const DID = 'did:plc:abc234567abcdefghijklmno';

	const OTHER_DID = 'did:plc:zzz234567abcdefghijklmno';

	const HANDLE = 'usoritual.com';

	const WELL_KNOWN = 'https://usoritual.com/.well-known/atproto-did';

	const PLC = 'https://plc.directory/did:plc:abc234567abcdefghijklmno';

	const EUROSKY_PDS = 'https://pds.eurosky.social';

	public function test_handle_by_dns() {
		$this->mock_network( array(), array( '_atproto.usoritual.com' => array( 'did=' . self::DID ) ) );

		$this->assertSame( self::DID, Identity::resolve_handle( 'UsoRitual.com' ) );
		$this->assertSame( array( '_atproto.usoritual.com' ), $this->dns_lookups );
		$this->assertSame( array(), $this->requests );
	}

	public function test_handle_by_https() {
		$this->mock_network( array( self::WELL_KNOWN => self::response( 200, self::DID . "\n" ) ) );

		$this->assertSame( self::DID, Identity::resolve_handle( self::HANDLE ) );

		$args = $this->request_to( self::WELL_KNOWN )['args'];
		$this->assertSame( 'GET', $args['method'] );
		$this->assertSame( 5, $args['timeout'] );
		$this->assertSame( 2, $args['redirection'] );
		$this->assertSame( 65536, $args['limit_response_size'] );
		$this->assertTrue( $args['reject_unsafe_urls'] );
	}

	public function test_several_dns_records_are_discarded() {
		$this->mock_network(
			array( self::WELL_KNOWN => self::response( 404 ) ),
			array( '_atproto.usoritual.com' => array( 'did=' . self::DID, 'did=' . self::OTHER_DID ) )
		);

		$result = Identity::resolve_handle( self::HANDLE );
		$this->assertWPError( $result );
		$this->assertSame( 'atproto_comments_handle_unresolved', $result->get_error_code() );
	}

	public function test_several_dns_records_fall_back_to_https() {
		$this->mock_network(
			array( self::WELL_KNOWN => self::response( 200, self::OTHER_DID ) ),
			array( '_atproto.usoritual.com' => array( 'did=' . self::DID, 'did=' . self::DID ) )
		);

		$this->assertSame( self::OTHER_DID, Identity::resolve_handle( self::HANDLE ) );
	}

	public function test_other_txt_records_are_ignored() {
		$this->mock_network( array(), array( '_atproto.usoritual.com' => array( 'v=spf1 -all', 'did=' . self::DID ) ) );

		$this->assertSame( self::DID, Identity::resolve_handle( self::HANDLE ) );
	}

	/**
	 * Bodies of /.well-known/atproto-did that are not a DID.
	 *
	 * @return array
	 */
	public function invalid_bodies() {
		return array(
			'html'      => array( '<!doctype html><html><body>Not found</body></html>' ),
			'empty'     => array( '' ),
			'other did' => array( 'did:key:z6Mkabc' ),
			'json'      => array( '{"did":"did:plc:abc234567abcdefghijklmno"}' ),
		);
	}

	/**
	 * A body that is not a DID does not resolve.
	 *
	 * @dataProvider invalid_bodies
	 *
	 * @param string $body Body.
	 */
	public function test_body_that_is_not_a_did( $body ) {
		$this->mock_network( array( self::WELL_KNOWN => self::response( 200, $body ) ) );

		$this->assertSame( 'atproto_comments_handle_unresolved', Identity::resolve_handle( self::HANDLE )->get_error_code() );
	}

	public function test_handle_without_resolution() {
		$this->mock_network( array( 'https://no-existe.example/.well-known/atproto-did' => new WP_Error( 'http_request_failed', 'Could not resolve host' ) ) );

		$result = Identity::resolve_handle( 'no-existe.example' );
		$this->assertWPError( $result );
		$this->assertSame( 'atproto_comments_handle_unresolved', $result->get_error_code() );
		$this->assertNotEmpty( $result->get_error_message() );
		$this->assertSame( 502, $result->get_error_data()['status'] );
	}

	public function test_invalid_handle_makes_no_request() {
		$this->mock_network( array() );

		$this->assertWPError( Identity::resolve_handle( 'no es un handle' ) );
		$this->assertWPError( Identity::resolve_handle( 'localhost' ) );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->dns_lookups );
	}

	public function test_deadline_caps_timeout() {
		$now = 1000.0;
		$this->mock_network( array( self::WELL_KNOWN => self::response( 200, self::DID ) ) );
		$deadline = new Deadline(
			3,
			static function () use ( &$now ) {
				return $now;
			}
		);

		$this->assertSame( self::DID, Identity::resolve_handle( self::HANDLE, $deadline ) );
		$this->assertSame( 3.0, $this->request_to( self::WELL_KNOWN )['args']['timeout'] );
	}

	public function test_expired_deadline_makes_no_request() {
		$now = 1000.0;
		$this->mock_network( array( self::WELL_KNOWN => self::response( 200, self::DID ) ) );
		$deadline = new Deadline(
			1,
			static function () use ( &$now ) {
				return $now;
			}
		);

		$this->assertSame( 'atproto_comments_timeout', Identity::resolve_handle( self::HANDLE, $deadline )->get_error_code() );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( array(), $this->dns_lookups );
	}

	public function test_did_plc_on_a_pds_that_is_not_bluesky() {
		$this->mock_network( array( self::PLC => self::response( 200, self::did_document( self::DID, self::EUROSKY_PDS . '/', array( self::HANDLE ) ) ) ) );

		$this->assertSame(
			array(
				'did'     => self::DID,
				'pds'     => self::EUROSKY_PDS,
				'handles' => array( self::HANDLE ),
			),
			Identity::resolve_did( self::DID )
		);
	}

	public function test_did_web_with_port() {
		$did = 'did:web:example.com%3A8443';
		$url = 'https://example.com:8443/.well-known/did.json';
		$this->mock_network( array( $url => self::response( 200, self::did_document( $did, 'https://pds.example.com', array() ) ) ) );

		$result = Identity::resolve_did( $did );
		$this->assertSame( 'https://pds.example.com', $result['pds'] );
		$this->assertNotNull( $this->request_to( $url ) );
	}

	public function test_full_service_id_is_accepted() {
		$doc                     = self::did_document( self::DID, self::EUROSKY_PDS, array() );
		$doc['service'][0]['id'] = self::DID . '#atproto_pds';
		$this->mock_network( array( self::PLC => self::response( 200, $doc ) ) );

		$this->assertSame( self::EUROSKY_PDS, Identity::resolve_did( self::DID )['pds'] );
	}

	/**
	 * DID documents without a usable PDS.
	 *
	 * @return array
	 */
	public function documents_without_pds() {
		$doc = self::did_document( self::DID, 'https://pds.example.com', array() );

		$no_service            = $doc;
		$no_service['service'] = array();

		$other_id                     = $doc;
		$other_id['service'][0]['id'] = '#atproto_labeler';

		$other_type                       = $doc;
		$other_type['service'][0]['type'] = 'AtprotoLabeler';

		$http                                       = $doc;
		$http['service'][0]['serviceEndpoint']      = 'http://pds.example.com';
		$not_a_url                                  = $doc;
		$not_a_url['service'][0]['serviceEndpoint'] = 'pds.example.com';

		return array(
			'no service' => array( $no_service ),
			'other id'   => array( $other_id ),
			'other type' => array( $other_type ),
			'http'       => array( $http ),
			'not a url'  => array( $not_a_url ),
		);
	}

	/**
	 * A document without `#atproto_pds` fails with no_pds.
	 *
	 * @dataProvider documents_without_pds
	 *
	 * @param array $doc DID document.
	 */
	public function test_document_without_pds( $doc ) {
		$this->mock_network( array( self::PLC => self::response( 200, $doc ) ) );

		$this->assertSame( 'atproto_comments_no_pds', Identity::resolve_did( self::DID )->get_error_code() );
	}

	public function test_document_id_must_match() {
		$this->mock_network( array( self::PLC => self::response( 200, self::did_document( self::OTHER_DID, self::EUROSKY_PDS, array() ) ) ) );

		$this->assertSame( 'atproto_comments_did_unresolved', Identity::resolve_did( self::DID )->get_error_code() );
	}

	public function test_did_not_found() {
		$this->mock_network( array( self::PLC => self::response( 404, array( 'message' => 'DID not registered' ) ) ) );

		$this->assertSame( 'atproto_comments_did_unresolved', Identity::resolve_did( self::DID )->get_error_code() );
	}

	public function test_invalid_json() {
		$this->mock_network( array( self::PLC => self::response( 200, '{not json' ) ) );

		$this->assertSame( 'atproto_comments_did_unresolved', Identity::resolve_did( self::DID )->get_error_code() );
	}

	public function test_account_with_handle() {
		$this->mock_network(
			array( self::PLC => self::response( 200, self::did_document( self::DID, self::EUROSKY_PDS, array( self::HANDLE ) ) ) ),
			array( '_atproto.usoritual.com' => array( 'did=' . self::DID ) )
		);

		$this->assertSame(
			array(
				'handle' => self::HANDLE,
				'did'    => self::DID,
				'pds'    => self::EUROSKY_PDS,
			),
			Identity::resolve_account( self::HANDLE )
		);
	}

	public function test_handle_missing_from_also_known_as() {
		$this->mock_network(
			array( self::PLC => self::response( 200, self::did_document( self::DID, self::EUROSKY_PDS, array( 'other.example' ) ) ) ),
			array( '_atproto.usoritual.com' => array( 'did=' . self::DID ) )
		);

		$result = Identity::resolve_account( self::HANDLE );
		$this->assertWPError( $result );
		$this->assertSame( 'atproto_comments_handle_mismatch', $result->get_error_code() );
	}

	public function test_account_with_did_shows_first_handle() {
		$this->mock_network( array( self::PLC => self::response( 200, self::did_document( self::DID, self::EUROSKY_PDS, array( 'First.example', 'second.example' ) ) ) ) );

		$this->assertSame(
			array(
				'handle' => 'first.example',
				'did'    => self::DID,
				'pds'    => self::EUROSKY_PDS,
			),
			Identity::resolve_account( self::DID )
		);
		$this->assertSame( array(), $this->dns_lookups );
	}

	public function test_account_without_pds_does_not_continue() {
		$doc            = self::did_document( self::DID, self::EUROSKY_PDS, array( self::HANDLE ) );
		$doc['service'] = array();
		$this->mock_network( array( self::PLC => self::response( 200, $doc ) ) );

		$this->assertSame( 'atproto_comments_no_pds', Identity::resolve_account( self::DID )->get_error_code() );
		$this->assertCount( 1, $this->requests );
	}
}
