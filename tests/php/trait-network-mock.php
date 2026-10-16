<?php
/**
 * Simulated network for tests: HTTP through `pre_http_request` and DNS
 * through `atproto_comments_pre_dns_txt`. Nothing reaches the real network.
 *
 * @package AtprotoComments
 */

/**
 * Records requests and answers them from a URL map.
 */
trait Atproto_Comments_Network_Mock {

	/**
	 * Requests made: each with `url` and `args`.
	 *
	 * @var array
	 */
	protected $requests = array();

	/**
	 * DNS lookups made (hostnames).
	 *
	 * @var string[]
	 */
	protected $dns_lookups = array();

	/**
	 * Simulates the network.
	 *
	 * @param array $http URL => response (from response()), WP_Error, or a
	 *                    callable receiving ( $args, $url ). Unknown URLs fail
	 *                    with a transport error.
	 * @param array $dns  Hostname => list of TXT strings. Unknown hostnames
	 *                    have no records.
	 */
	protected function mock_network( array $http, array $dns = array() ) {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $http ) {
				$this->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				if ( ! array_key_exists( $url, $http ) ) {
					return new WP_Error( 'http_request_failed', 'Unexpected request to ' . $url );
				}
				$answer = $http[ $url ];
				return is_callable( $answer ) ? call_user_func( $answer, $args, $url ) : $answer;
			},
			10,
			3
		);
		add_filter(
			'atproto_comments_pre_dns_txt',
			function ( $records, $hostname ) use ( $dns ) {
				$this->dns_lookups[] = $hostname;
				return isset( $dns[ $hostname ] ) ? $dns[ $hostname ] : array();
			},
			10,
			2
		);
	}

	/**
	 * HTTP response for pre_http_request.
	 *
	 * @param int          $code Status.
	 * @param string|array $body Body; arrays are encoded as JSON.
	 * @return array
	 */
	protected static function response( $code, $body = '' ) {
		return array(
			'headers'  => array(),
			'body'     => is_array( $body ) ? wp_json_encode( $body ) : $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * A DID document.
	 *
	 * @param string $did     DID.
	 * @param string $pds     PDS URL.
	 * @param array  $handles Handles in alsoKnownAs.
	 * @return array
	 */
	protected static function did_document( $did, $pds, array $handles ) {
		return array(
			'@context'    => array( 'https://www.w3.org/ns/did/v1' ),
			'id'          => $did,
			'alsoKnownAs' => array_map(
				static function ( $handle ) {
					return 'at://' . $handle;
				},
				$handles
			),
			'service'     => array(
				array(
					'id'              => '#atproto_pds',
					'type'            => 'AtprotoPersonalDataServer',
					'serviceEndpoint' => $pds,
				),
			),
		);
	}

	/**
	 * Request made to a URL, or null.
	 *
	 * @param string $url URL.
	 * @return array|null
	 */
	protected function request_to( $url ) {
		foreach ( $this->requests as $request ) {
			if ( $request['url'] === $url ) {
				return $request;
			}
		}
		return null;
	}
}
