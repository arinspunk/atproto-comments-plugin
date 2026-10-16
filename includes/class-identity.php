<?php
/**
 * AT Protocol identity resolution: handle → DID → DID document → PDS.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves handles and DIDs with the standard methods, without a fixed PDS or
 * AppView. Every request goes through wp_safe_remote_get(), which rejects
 * private and loopback addresses.
 */
final class Identity {

	/**
	 * Timeout of each request, in seconds, before the deadline cap.
	 */
	const TIMEOUT = 5;

	const MAX_REDIRECTS = 2;

	const MAX_RESPONSE_BYTES = 65536;

	/**
	 * Resolves a handle to its DID, first with the `_atproto` DNS TXT record
	 * and then with `https://<handle>/.well-known/atproto-did`.
	 *
	 * @param string        $handle   Handle.
	 * @param Deadline|null $deadline Global deadline, if any.
	 * @return string|\WP_Error The DID.
	 */
	public static function resolve_handle( $handle, $deadline = null ) {
		$handle = strtolower( trim( (string) $handle ) );
		if ( ! self::is_handle( $handle ) ) {
			return self::error_handle_unresolved();
		}

		if ( $deadline && ! $deadline->has( Deadline::MIN_REQUEST ) ) {
			return Deadline::error();
		}
		$did = self::did_from_dns( $handle );
		if ( null !== $did ) {
			return $did;
		}

		if ( $deadline && ! $deadline->has( Deadline::MIN_REQUEST ) ) {
			return Deadline::error();
		}
		$body = self::get( 'https://' . $handle . '/.well-known/atproto-did', $deadline );
		if ( is_string( $body ) ) {
			$lines = preg_split( '/\r?\n/', $body, 2 );
			$did   = trim( $lines[0] );
			if ( self::is_did( $did ) ) {
				return $did;
			}
		}

		return self::error_handle_unresolved();
	}

	/**
	 * Fetches the DID document and extracts the PDS and the handles.
	 *
	 * @param string        $did      DID (`did:plc` or `did:web`).
	 * @param Deadline|null $deadline Global deadline, if any.
	 * @return array{did: string, pds: string, handles: string[]}|\WP_Error
	 */
	public static function resolve_did( $did, $deadline = null ) {
		$did = (string) $did;
		if ( ! self::is_did( $did ) ) {
			return self::error_did_unresolved();
		}

		if ( 0 === strpos( $did, 'did:plc:' ) ) {
			$url = 'https://plc.directory/' . $did;
		} else {
			$host = str_replace( '%3A', ':', substr( $did, strlen( 'did:web:' ) ) );
			$url  = 'https://' . $host . '/.well-known/did.json';
		}

		if ( $deadline && ! $deadline->has( Deadline::MIN_REQUEST ) ) {
			return Deadline::error();
		}
		$body = self::get( $url, $deadline );
		if ( ! is_string( $body ) ) {
			return self::error_did_unresolved();
		}

		$doc = json_decode( $body, true );
		if ( ! is_array( $doc ) || ! isset( $doc['id'] ) || $doc['id'] !== $did ) {
			return self::error_did_unresolved();
		}

		$pds = self::pds_from_document( $doc, $did );
		if ( null === $pds ) {
			return new \WP_Error(
				'atproto_comments_no_pds',
				__( 'The account\'s DID document does not declare an AT Protocol server (PDS).', 'atproto-comments' ),
				array( 'status' => 502 )
			);
		}

		$handles = array();
		if ( isset( $doc['alsoKnownAs'] ) && is_array( $doc['alsoKnownAs'] ) ) {
			foreach ( $doc['alsoKnownAs'] as $aka ) {
				if ( is_string( $aka ) && 0 === strpos( $aka, 'at://' ) ) {
					$handles[] = strtolower( substr( $aka, strlen( 'at://' ) ) );
				}
			}
		}

		return array(
			'did'     => $did,
			'pds'     => $pds,
			'handles' => $handles,
		);
	}

	/**
	 * Resolves the site account. A handle must be declared back in the DID
	 * document (`alsoKnownAs`); with a DID, the shown handle is the first one
	 * the document declares.
	 *
	 * @param string        $identifier Handle or DID.
	 * @param Deadline|null $deadline   Global deadline, if any.
	 * @return array{handle: string, did: string, pds: string}|\WP_Error
	 */
	public static function resolve_account( $identifier, $deadline = null ) {
		$identifier = trim( (string) $identifier );

		if ( 0 === strpos( $identifier, 'did:' ) ) {
			$doc = self::resolve_did( $identifier, $deadline );
			if ( is_wp_error( $doc ) ) {
				return $doc;
			}
			$handle = '';
			foreach ( $doc['handles'] as $candidate ) {
				if ( self::is_handle( $candidate ) ) {
					$handle = $candidate;
					break;
				}
			}
			return array(
				'handle' => $handle,
				'did'    => $doc['did'],
				'pds'    => $doc['pds'],
			);
		}

		$handle = strtolower( $identifier );
		$did    = self::resolve_handle( $handle, $deadline );
		if ( is_wp_error( $did ) ) {
			return $did;
		}
		$doc = self::resolve_did( $did, $deadline );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		if ( ! in_array( $handle, $doc['handles'], true ) ) {
			return new \WP_Error(
				'atproto_comments_handle_mismatch',
				__( 'The handle is not verified: its DID document does not declare it. Check the handle or use the DID.', 'atproto-comments' ),
				array( 'status' => 502 )
			);
		}

		return array(
			'handle' => $handle,
			'did'    => $doc['did'],
			'pds'    => $doc['pds'],
		);
	}

	/**
	 * Whether a value is a syntactically valid handle.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_handle( $value ) {
		return is_string( $value )
			&& strlen( $value ) <= 253
			&& 0 !== strpos( $value, 'did:' )
			&& 1 === preg_match( '/^' . Thread_Uri::HANDLE . '\z/', $value );
	}

	/**
	 * Whether a value is a supported DID.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_did( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^(?:' . Thread_Uri::DID . ')\z/', $value );
	}

	/**
	 * DID from the `_atproto.<handle>` TXT record. Several `did=` records
	 * make the result ambiguous, so it is discarded.
	 *
	 * @param string $handle Handle.
	 * @return string|null
	 */
	private static function did_from_dns( $handle ) {
		$dids = array();
		foreach ( self::txt_records( '_atproto.' . $handle ) as $txt ) {
			if ( 0 === strpos( $txt, 'did=' ) ) {
				$dids[] = substr( $txt, strlen( 'did=' ) );
			}
		}
		if ( 1 !== count( $dids ) || ! self::is_did( $dids[0] ) ) {
			return null;
		}
		return $dids[0];
	}

	/**
	 * TXT records of a hostname. The internal filter
	 * `atproto_comments_pre_dns_txt` replaces the lookup in tests.
	 *
	 * @param string $hostname Hostname.
	 * @return string[]
	 */
	private static function txt_records( $hostname ) {
		$records = apply_filters( 'atproto_comments_pre_dns_txt', null, $hostname );
		if ( is_array( $records ) ) {
			return array_values( array_filter( $records, 'is_string' ) );
		}

		if ( ! function_exists( 'dns_get_record' ) ) {
			return array();
		}
		$found = @dns_get_record( $hostname, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A failed lookup emits a warning; it is handled as no records.
		if ( ! is_array( $found ) ) {
			return array();
		}

		$records = array();
		foreach ( $found as $record ) {
			if ( isset( $record['entries'] ) && is_array( $record['entries'] ) ) {
				$records[] = implode( '', $record['entries'] );
			} elseif ( isset( $record['txt'] ) ) {
				$records[] = (string) $record['txt'];
			}
		}
		return $records;
	}

	/**
	 * PDS endpoint from a DID document: the `#atproto_pds` service of type
	 * `AtprotoPersonalDataServer`, with an https URL.
	 *
	 * @param array  $doc DID document.
	 * @param string $did DID.
	 * @return string|null URL without a trailing slash.
	 */
	private static function pds_from_document( $doc, $did ) {
		if ( ! isset( $doc['service'] ) || ! is_array( $doc['service'] ) ) {
			return null;
		}
		foreach ( $doc['service'] as $service ) {
			if ( ! is_array( $service )
				|| ! isset( $service['id'], $service['type'], $service['serviceEndpoint'] )
				|| ! in_array( $service['id'], array( '#atproto_pds', $did . '#atproto_pds' ), true )
				|| 'AtprotoPersonalDataServer' !== $service['type']
				|| ! is_string( $service['serviceEndpoint'] )
			) {
				continue;
			}
			$url   = rtrim( $service['serviceEndpoint'], '/' );
			$parts = wp_parse_url( $url );
			if ( is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && 'https' === strtolower( $parts['scheme'] ) ) {
				return $url;
			}
		}
		return null;
	}

	/**
	 * GET request with the identity limits.
	 *
	 * @param string        $url      URL.
	 * @param Deadline|null $deadline Global deadline, if any.
	 * @return string|null Body of a 200 response, or null.
	 */
	private static function get( $url, $deadline ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => $deadline ? $deadline->timeout( self::TIMEOUT ) : self::TIMEOUT,
				'redirection'         => self::MAX_REDIRECTS,
				'limit_response_size' => self::MAX_RESPONSE_BYTES,
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Error for a handle that does not resolve.
	 *
	 * @return \WP_Error
	 */
	private static function error_handle_unresolved() {
		return new \WP_Error(
			'atproto_comments_handle_unresolved',
			__( 'The handle does not exist or could not be resolved to a DID.', 'atproto-comments' ),
			array( 'status' => 502 )
		);
	}

	/**
	 * Error for a DID whose document cannot be obtained or is not valid.
	 *
	 * @return \WP_Error
	 */
	private static function error_did_unresolved() {
		return new \WP_Error(
			'atproto_comments_did_unresolved',
			__( 'The account\'s DID document could not be obtained or is not valid.', 'atproto-comments' ),
			array( 'status' => 502 )
		);
	}
}
