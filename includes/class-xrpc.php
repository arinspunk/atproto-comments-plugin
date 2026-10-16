<?php
/**
 * Minimal XRPC client for the account's PDS.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * XRPC procedures (POST) without redirects. PDS errors become WP_Error with
 * a translated message and a REST status; the PDS `message` is never shown.
 */
final class Xrpc {

	/**
	 * Timeout of a JSON procedure, in seconds, before the deadline cap.
	 */
	const TIMEOUT = 10;

	/**
	 * Timeout of uploadBlob, in seconds, before the deadline cap.
	 */
	const UPLOAD_TIMEOUT = 20;

	const MAX_RESPONSE_BYTES = 1048576;

	/**
	 * Calls a JSON procedure.
	 *
	 * @param string        $pds      PDS URL.
	 * @param string        $nsid     Method, e.g. `com.atproto.repo.createRecord`.
	 * @param array         $body     Input.
	 * @param string|null   $token    Access token, if any.
	 * @param Deadline|null $deadline Global deadline, if any.
	 * @return array|\WP_Error Decoded output.
	 */
	public static function procedure( $pds, $nsid, $body, $token = null, $deadline = null ) {
		return self::post( $pds, $nsid, wp_json_encode( $body ), 'application/json', $token, $deadline, self::TIMEOUT );
	}

	/**
	 * Uploads a blob.
	 *
	 * @param string        $pds      PDS URL.
	 * @param string        $bytes    File contents.
	 * @param string        $mime     MIME type.
	 * @param string        $token    Access token.
	 * @param Deadline|null $deadline Global deadline, if any.
	 * @return array|\WP_Error Decoded output, with the blob in `blob`.
	 */
	public static function upload_blob( $pds, $bytes, $mime, $token, $deadline = null ) {
		return self::post( $pds, 'com.atproto.repo.uploadBlob', $bytes, $mime, $token, $deadline, self::UPLOAD_TIMEOUT );
	}

	/**
	 * Opens a session. The identifier is always the resolved DID, so the
	 * session does not depend on the PDS having the handle up to date.
	 *
	 * @param string        $pds      PDS URL.
	 * @param string        $did      Resolved DID.
	 * @param string        $password App password.
	 * @param Deadline|null $deadline Global deadline, if any.
	 * @return array{access_jwt: string, did: string}|\WP_Error
	 */
	public static function create_session( $pds, $did, $password, $deadline = null ) {
		$session = self::procedure(
			$pds,
			'com.atproto.server.createSession',
			array(
				'identifier' => $did,
				'password'   => $password,
			),
			null,
			$deadline
		);
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		if ( ! isset( $session['accessJwt'], $session['did'] ) || ! is_string( $session['accessJwt'] ) || $session['did'] !== $did ) {
			return self::error( 'atproto_comments_server' );
		}
		return array(
			'access_jwt' => $session['accessJwt'],
			'did'        => $session['did'],
		);
	}

	/**
	 * POST request to an XRPC method.
	 *
	 * @param string        $pds          PDS URL.
	 * @param string        $nsid         Method.
	 * @param string        $body         Request body.
	 * @param string        $content_type Content type.
	 * @param string|null   $token        Access token, if any.
	 * @param Deadline|null $deadline     Global deadline, if any.
	 * @param int           $timeout      Maximum timeout.
	 * @return array|\WP_Error
	 */
	private static function post( $pds, $nsid, $body, $content_type, $token, $deadline, $timeout ) {
		if ( $deadline ) {
			if ( ! $deadline->has( Deadline::MIN_REQUEST ) ) {
				return Deadline::error();
			}
			$timeout = $deadline->timeout( $timeout );
		}

		$headers = array(
			'Content-Type' => $content_type,
			'Accept'       => 'application/json',
		);
		if ( null !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_safe_remote_post(
			rtrim( $pds, '/' ) . '/xrpc/' . $nsid,
			array(
				'timeout'             => $timeout,
				'redirection'         => 0,
				'limit_response_size' => self::MAX_RESPONSE_BYTES,
				'headers'             => $headers,
				'body'                => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return self::error( 'atproto_comments_network' );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 ) {
			return is_array( $data ) ? $data : self::error( 'atproto_comments_server' );
		}

		$name = is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ? $data['error'] : '';
		return self::error( self::error_code( $status, $name ), $name );
	}

	/**
	 * Plugin error code for a PDS error response.
	 *
	 * @param int    $status HTTP status.
	 * @param string $name   XRPC `error` value.
	 * @return string
	 */
	private static function error_code( $status, $name ) {
		if ( 'AuthFactorTokenRequired' === $name ) {
			return 'atproto_comments_auth_factor';
		}
		if ( in_array( $name, array( 'AccountTakedown', 'AccountDeactivated' ), true ) ) {
			return 'atproto_comments_account_unavailable';
		}
		if ( 429 === $status ) {
			return 'atproto_comments_rate_limited';
		}
		if ( 401 === $status || in_array( $name, array( 'AuthenticationRequired', 'InvalidPassword' ), true ) ) {
			return 'atproto_comments_auth';
		}
		return 'atproto_comments_server';
	}

	/**
	 * Builds an error with its translated message and REST status. The XRPC
	 * error name, if it is a plain identifier, is appended for support.
	 *
	 * @param string $code Error code.
	 * @param string $name XRPC `error` value, if any.
	 * @return \WP_Error
	 */
	public static function error( $code, $name = '' ) {
		switch ( $code ) {
			case 'atproto_comments_network':
				$message = __( 'The AT Protocol server did not respond. Try again later.', 'atproto-comments' );
				break;
			case 'atproto_comments_auth':
				$message = __( 'The AT Protocol server rejected the credentials. Check the account in the settings.', 'atproto-comments' );
				break;
			case 'atproto_comments_auth_factor':
				$message = __( 'The account asks for a sign-in code. Use an app password instead of the main password.', 'atproto-comments' );
				break;
			case 'atproto_comments_account_unavailable':
				$message = __( 'The AT Protocol account is suspended or deactivated.', 'atproto-comments' );
				break;
			case 'atproto_comments_rate_limited':
				$message = __( 'The AT Protocol server has received too many requests. Try again in a few minutes.', 'atproto-comments' );
				break;
			default:
				$code    = 'atproto_comments_server';
				$message = __( 'The AT Protocol server returned an unexpected response.', 'atproto-comments' );
		}

		if ( '' !== $name && preg_match( '/^[A-Za-z][A-Za-z0-9]{0,63}\z/', $name ) ) {
			$message .= ' (' . $name . ')';
		}

		return new \WP_Error( $code, $message, array( 'status' => 'atproto_comments_rate_limited' === $code ? 429 : 502 ) );
	}
}
