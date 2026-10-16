<?php
/**
 * Thread links with handles: resolves the handle to a DID before saving.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps Thread_Uri (pure, without network) with handle resolution. Resolved
 * handles are cached for 10 minutes; failures are not cached, so a handle that
 * was just set up works on the next try.
 */
final class Thread_Link {

	const CACHE_PREFIX = 'atproto_comments_handle_';

	const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * Normalizes a thread link and resolves its handle, if it has one.
	 *
	 * @param string $raw Raw input.
	 * @return array{ok: bool, uri?: string, error?: string} On success `uri`
	 *               is the canonical at:// URI ('' unlinks); on failure
	 *               `error` is 'format' or 'handle_unresolved'.
	 */
	public static function resolve( $raw ) {
		$result = Thread_Uri::normalize( $raw );
		if ( $result['ok'] || 'handle' !== $result['error'] ) {
			return $result;
		}

		$did = self::resolve_handle( $result['handle'] );
		if ( is_wp_error( $did ) ) {
			return array(
				'ok'    => false,
				'error' => 'handle_unresolved',
			);
		}

		return array(
			'ok'  => true,
			'uri' => 'at://' . $did . '/' . Thread_Uri::COLLECTION . '/' . $result['rkey'],
		);
	}

	/**
	 * Resolves a handle to its DID, using the cache.
	 *
	 * @param string $handle Handle.
	 * @return string|\WP_Error
	 */
	public static function resolve_handle( $handle ) {
		$handle = strtolower( trim( (string) $handle ) );
		$key    = self::CACHE_PREFIX . md5( $handle );

		$cached = get_transient( $key );
		if ( is_string( $cached ) && Identity::is_did( $cached ) ) {
			return $cached;
		}

		$did = Identity::resolve_handle( $handle );
		if ( ! is_wp_error( $did ) ) {
			set_transient( $key, $did, self::CACHE_TTL );
		}
		return $did;
	}

	/**
	 * Registers the REST route used by the block editor panel.
	 */
	public static function register_routes() {
		register_rest_route(
			Account::REST_NAMESPACE,
			'/resolve-handle',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_resolve_handle' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'handle' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => static function ( $value ) {
							return strtolower( trim( (string) $value ) );
						},
						'validate_callback' => static function ( $value ) {
							return Identity::is_handle( strtolower( trim( (string) $value ) ) );
						},
					),
				),
			)
		);
	}

	/**
	 * Resolves a handle for the panel.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error `{ did }`.
	 */
	public static function rest_resolve_handle( $request ) {
		$did = self::resolve_handle( $request['handle'] );
		if ( is_wp_error( $did ) ) {
			return $did;
		}
		return new \WP_REST_Response( array( 'did' => $did ) );
	}
}
