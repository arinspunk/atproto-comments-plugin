<?php
/**
 * Publishing the anchor post of an entry from the editor.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route that creates the `app.bsky.feed.post` of an entry in the site
 * account and links it (POST), or deletes it and unlinks the entry (DELETE).
 * The browser only sends the post ID (and the text, to publish); everything
 * else comes from the server.
 */
final class Anchor_Post {

	const MAX_GRAPHEMES = 300;

	const MAX_BYTES = 3000;

	const LOCK_PREFIX = 'atproto_comments_lock_';

	const LOCK_TTL = 120;

	const IMAGE_SIZE = 'medium_large';

	const IMAGE_MAX_BYTES = 1000000;

	const IMAGE_TYPES = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );

	/**
	 * Seconds that must be left to upload the image: 10 are kept for
	 * createRecord.
	 */
	const IMAGE_MIN_TIME = 12;

	/**
	 * Seconds that must be left to confirm an inconclusive deletion with
	 * getRecord.
	 */
	const CONFIRM_MIN_TIME = 3;

	/**
	 * Clock for the deadline. Only tests replace it.
	 *
	 * @var callable|null
	 */
	private static $clock = null;

	/**
	 * Replaces the clock of the deadline (tests only). Null restores it.
	 *
	 * @param callable|null $clock Clock returning seconds as a float.
	 */
	public static function set_clock( $clock ) {
		self::$clock = $clock;
	}

	/**
	 * Whether the current user can publish the anchor post of an entry: they
	 * can edit it and have the publish capability (by default
	 * `edit_others_posts`, i.e. editors and administrators).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function user_can_publish( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return false;
		}

		/**
		 * Capability needed to publish an anchor post in the site account,
		 * besides `edit_post` on the entry.
		 *
		 * @param string   $capability Capability. Default 'edit_others_posts'.
		 * @param \WP_Post $post       Entry.
		 */
		$capability = apply_filters( 'atproto_comments_publish_capability', 'edit_others_posts', $post );

		return is_string( $capability ) && '' !== $capability && current_user_can( $capability, $post->ID );
	}

	/**
	 * Whether the current user can delete the anchor post of an entry: they
	 * can publish it and can also delete the entry.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function user_can_delete( $post_id ) {
		return self::user_can_publish( $post_id ) && current_user_can( 'delete_post', (int) $post_id );
	}

	/**
	 * Registers the REST route.
	 */
	public static function register_routes() {
		register_rest_route(
			Account::REST_NAMESPACE,
			'/posts/(?P<id>\d+)/anchor-post',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_publish' ),
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
					'args'                => array(
						'text' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'rest_delete' ),
					'permission_callback' => array( __CLASS__, 'rest_delete_permission' ),
				),
			)
		);
	}

	/**
	 * Permission of the route. Without the REST nonce, a cookie-authenticated
	 * user is treated as anonymous and fails here.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function rest_permission( $request ) {
		return self::check_permission( $request, array( __CLASS__, 'user_can_publish' ) );
	}

	/**
	 * Permission of the DELETE endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function rest_delete_permission( $request ) {
		return self::check_permission( $request, array( __CLASS__, 'user_can_delete' ) );
	}

	/**
	 * A 404 for a missing entry to users who can edit entries, and the given
	 * check otherwise.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param callable         $check   Receives the post ID.
	 * @return bool|\WP_Error
	 */
	private static function check_permission( $request, $check ) {
		$post_id = (int) $request['id'];
		if ( ! get_post( $post_id ) && current_user_can( 'edit_posts' ) ) {
			return self::error_not_found();
		}
		return (bool) call_user_func( $check, $post_id );
	}

	/**
	 * Publishes the anchor post and links it.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error `{ uri }` with status 201.
	 */
	public static function rest_publish( $request ) {
		$post = get_post( (int) $request['id'] );
		$text = (string) $request['text'];

		$invalid = self::validate( $post, $text );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}

		if ( ! self::acquire_lock( $post->ID ) ) {
			return self::error_locked();
		}

		try {
			// Another request may have linked the entry while this one waited.
			wp_cache_delete( $post->ID, 'post_meta' );
			if ( self::has_link( $post ) ) {
				return self::error_linked();
			}
			$result = self::publish( $post, $text );
		} finally {
			self::release_lock( $post->ID );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( array( 'uri' => $result ), 201 );
	}

	/**
	 * Deletes the anchor post and unlinks the entry. The repo and the rkey
	 * come from the saved link, never from the request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error `{ uri: '' }`.
	 */
	public static function rest_delete( $request ) {
		$post = get_post( (int) $request['id'] );

		$invalid = self::validate_delete( $post );
		if ( is_wp_error( $invalid ) ) {
			return $invalid;
		}

		if ( ! self::acquire_lock( $post->ID ) ) {
			return self::error_locked();
		}

		try {
			// Another request may have changed the link while this one waited.
			wp_cache_delete( $post->ID, 'post_meta' );
			$uri    = self::saved_link( $post );
			$result = null === $uri ? self::error_not_linked() : self::delete( $post, $uri );
		} finally {
			self::release_lock( $post->ID );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( array( 'uri' => '' ), 200 );
	}

	/**
	 * Checks the preconditions of a deletion without contacting the PDS.
	 *
	 * @param \WP_Post|null $post Entry.
	 * @return true|\WP_Error
	 */
	private static function validate_delete( $post ) {
		if ( ! $post ) {
			return self::error_not_found();
		}
		if ( ! Settings::is_active_type( $post->post_type ) ) {
			return self::error_invalid( 'atproto_comments_inactive_type', __( 'AT Protocol comments are not enabled for this content type.', 'atproto-comments' ) );
		}
		if ( null === self::saved_link( $post ) ) {
			return self::error_not_linked();
		}
		if ( ! Account::is_configured() ) {
			return Account::error_not_configured();
		}
		return true;
	}

	/**
	 * Resolves the account, checks that the post is its own, opens a session
	 * and deletes the record, within one global deadline. The entry is
	 * unlinked only if the post no longer exists.
	 *
	 * @param \WP_Post $post Entry.
	 * @param string   $uri  Saved at:// URI.
	 * @return true|\WP_Error
	 */
	private static function delete( $post, $uri ) {
		$deadline = new Deadline( Deadline::DEFAULT_SECONDS, self::$clock );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 40 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Margin above the 25 s deadline; the design does not depend on it.
		}

		$account = Identity::resolve_account( Account::get()['identifier'], $deadline );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		Account::remember_did( $account['did'] );

		$parts = self::uri_parts( $uri );
		if ( $parts['did'] !== $account['did'] ) {
			return new \WP_Error(
				'atproto_comments_not_owner',
				__( 'The linked post does not belong to the site\'s AT Protocol account, so it cannot be deleted from here. You can unlink it.', 'atproto-comments' ),
				array( 'status' => 403 )
			);
		}

		$password = Crypto::decrypt( Account::get()['password'] );
		$session  = Xrpc::create_session( $account['pds'], $account['did'], (string) $password, $deadline );
		Crypto::wipe( $password );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$record = array(
			'repo'       => $parts['did'],
			'collection' => Thread_Uri::COLLECTION,
			'rkey'       => $parts['rkey'],
		);
		$output = Xrpc::procedure( $account['pds'], 'com.atproto.repo.deleteRecord', $record, $session['access_jwt'], $deadline );

		if ( is_wp_error( $output ) && 'atproto_comments_record_not_found' !== $output->get_error_code() ) {
			if ( ! self::is_inconclusive( $output ) ) {
				return $output;
			}
			$confirmed = self::confirm_deleted( $account['pds'], $record, $deadline );
			if ( is_wp_error( $confirmed ) ) {
				return $confirmed;
			}
		}

		delete_post_meta( $post->ID, Post_Meta::KEY );
		return true;
	}

	/**
	 * Whether a deleteRecord error does not say whether the post still
	 * exists: a transport error or timeout, or a 404 that is not
	 * `RecordNotFound` (e.g. a proxy page).
	 *
	 * @param \WP_Error $error Error.
	 * @return bool
	 */
	private static function is_inconclusive( $error ) {
		if ( 'atproto_comments_network' === $error->get_error_code() ) {
			return true;
		}
		$data = $error->get_error_data();
		return 'atproto_comments_server' === $error->get_error_code() && is_array( $data ) && isset( $data['pds_status'] ) && 404 === $data['pds_status'];
	}

	/**
	 * Checks with the public getRecord whether the post is gone.
	 *
	 * @param string   $pds      PDS URL.
	 * @param array    $record   `repo`, `collection` and `rkey`.
	 * @param Deadline $deadline Global deadline.
	 * @return true|\WP_Error True if the PDS says it does not exist.
	 */
	private static function confirm_deleted( $pds, $record, $deadline ) {
		if ( ! $deadline->has( self::CONFIRM_MIN_TIME ) ) {
			return self::error_delete_unconfirmed();
		}
		$output = Xrpc::query( $pds, 'com.atproto.repo.getRecord', $record, $deadline );
		if ( is_wp_error( $output ) ) {
			return 'atproto_comments_record_not_found' === $output->get_error_code() ? true : self::error_delete_unconfirmed();
		}
		if ( isset( $output['uri'] ) || isset( $output['value'] ) ) {
			return new \WP_Error(
				'atproto_comments_delete_failed',
				__( 'The post was not deleted: it is still on the AT Protocol server. Try again later.', 'atproto-comments' ),
				array( 'status' => 502 )
			);
		}
		return self::error_delete_unconfirmed();
	}

	/**
	 * Saved link of the entry, if it is a canonical at:// URI.
	 *
	 * @param \WP_Post $post Entry.
	 * @return string|null
	 */
	private static function saved_link( $post ) {
		$value = get_post_meta( $post->ID, Post_Meta::KEY, true );
		return is_string( $value ) && Thread_Uri::is_canonical( $value ) ? $value : null;
	}

	/**
	 * DID and rkey of a canonical at:// URI.
	 *
	 * @param string $uri at:// URI.
	 * @return array{did: string, rkey: string}
	 */
	private static function uri_parts( $uri ) {
		$path = explode( '/', substr( $uri, strlen( 'at://' ) ) );
		return array(
			'did'  => $path[0],
			'rkey' => $path[2],
		);
	}

	/**
	 * Checks the preconditions without contacting the PDS.
	 *
	 * @param \WP_Post|null $post Entry.
	 * @param string        $text Post text.
	 * @return true|\WP_Error
	 */
	private static function validate( $post, $text ) {
		if ( ! $post ) {
			return self::error_not_found();
		}
		if ( ! Settings::is_active_type( $post->post_type ) ) {
			return self::error_invalid( 'atproto_comments_inactive_type', __( 'AT Protocol comments are not enabled for this content type.', 'atproto-comments' ) );
		}
		if ( 'publish' !== $post->post_status ) {
			return self::error_invalid( 'atproto_comments_not_published', __( 'Publish the entry before publishing its anchor post.', 'atproto-comments' ) );
		}
		if ( self::has_link( $post ) ) {
			return self::error_linked();
		}
		if ( ! Account::is_configured() ) {
			return Account::error_not_configured();
		}

		$graphemes = Graphemes::count( $text );
		if ( '' === trim( $text ) || null === $graphemes ) {
			return self::error_invalid( 'atproto_comments_text_empty', __( 'Write the text of the post.', 'atproto-comments' ) );
		}
		if ( $graphemes > self::MAX_GRAPHEMES || strlen( $text ) > self::MAX_BYTES ) {
			return self::error_invalid(
				'atproto_comments_text_too_long',
				sprintf(
					/* translators: %d: maximum number of characters, 300. */
					__( 'The text of the post is too long. The limit is %d characters.', 'atproto-comments' ),
					self::MAX_GRAPHEMES
				)
			);
		}

		return true;
	}

	/**
	 * Resolves the account, opens a session, uploads the image and creates the
	 * post, within one global deadline, and saves the link.
	 *
	 * @param \WP_Post $post Entry.
	 * @param string   $text Post text.
	 * @return string|\WP_Error The at:// URI of the post.
	 */
	private static function publish( $post, $text ) {
		$deadline = new Deadline( Deadline::DEFAULT_SECONDS, self::$clock );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 40 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Margin above the 25 s deadline; the design does not depend on it.
		}

		$account = Identity::resolve_account( Account::get()['identifier'], $deadline );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		Account::remember_did( $account['did'] );

		$password = Crypto::decrypt( Account::get()['password'] );
		$session  = Xrpc::create_session( $account['pds'], $account['did'], (string) $password, $deadline );
		Crypto::wipe( $password );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$thumb  = self::upload_thumb( $post, $account['pds'], $session['access_jwt'], $deadline );
		$output = Xrpc::procedure(
			$account['pds'],
			'com.atproto.repo.createRecord',
			array(
				'repo'       => $account['did'],
				'collection' => Thread_Uri::COLLECTION,
				'record'     => self::build_record( $post, $text, $thumb ),
			),
			$session['access_jwt'],
			$deadline
		);

		if ( is_wp_error( $output ) ) {
			// The request was sent: the post may exist.
			return 'atproto_comments_network' === $output->get_error_code() ? self::error_uncertain() : $output;
		}
		if ( ! isset( $output['uri'] ) || ! Thread_Uri::is_canonical( $output['uri'] ) ) {
			return self::error_uncertain();
		}

		update_post_meta( $post->ID, Post_Meta::KEY, $output['uri'] );
		return $output['uri'];
	}

	/**
	 * The `app.bsky.feed.post` record: the text as is (no facets), the
	 * creation date, the site language and a card linking to the entry.
	 *
	 * @param \WP_Post   $post  Entry.
	 * @param string     $text  Post text.
	 * @param array|null $thumb Uploaded blob, if any.
	 * @return array
	 */
	public static function build_record( $post, $text, $thumb = null ) {
		$external = array(
			'uri'         => get_permalink( $post ),
			'title'       => self::plain_text( $post->post_title ),
			'description' => '' !== $post->post_password
				? ''
				: rtrim( Graphemes::truncate( self::plain_text( get_the_excerpt( $post ) ), self::MAX_GRAPHEMES ) ),
		);
		if ( is_array( $thumb ) ) {
			$external['thumb'] = $thumb;
		}

		$record = array(
			'$type'     => Thread_Uri::COLLECTION,
			'text'      => $text,
			'createdAt' => self::now_iso(),
		);
		$lang   = self::site_language();
		if ( '' !== $lang ) {
			$record['langs'] = array( $lang );
		}
		$record['embed'] = array(
			'$type'    => 'app.bsky.embed.external',
			'external' => $external,
		);

		return $record;
	}

	/**
	 * Uploads the featured image for the card, if there is time and it meets
	 * the limits. Any failure means a card without image.
	 *
	 * @param \WP_Post $post     Entry.
	 * @param string   $pds      PDS URL.
	 * @param string   $token    Access token.
	 * @param Deadline $deadline Global deadline.
	 * @return array|null The blob.
	 */
	private static function upload_thumb( $post, $pds, $token, $deadline ) {
		if ( ! $deadline->has( self::IMAGE_MIN_TIME ) ) {
			return null;
		}
		$image = self::thumb_file( $post );
		if ( null === $image ) {
			return null;
		}

		$bytes = file_get_contents( $image['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file of the media library.
		if ( false === $bytes ) {
			return null;
		}
		$output = Xrpc::upload_blob( $pds, $bytes, $image['mime'], $token, $deadline );
		if ( is_wp_error( $output ) || ! isset( $output['blob'] ) || ! is_array( $output['blob'] ) ) {
			return null;
		}
		return $output['blob'];
	}

	/**
	 * Local file of the featured image: the `medium_large` size, or the
	 * original if there is no such size. It must exist, have an allowed type
	 * and weigh at most 1 000 000 bytes.
	 *
	 * @param \WP_Post $post Entry.
	 * @return array{path: string, mime: string}|null
	 */
	public static function thumb_file( $post ) {
		$id = (int) get_post_thumbnail_id( $post );
		if ( ! $id ) {
			return null;
		}
		$original = get_attached_file( $id );
		if ( ! is_string( $original ) || '' === $original ) {
			return null;
		}

		$path = $original;
		$size = image_get_intermediate_size( $id, self::IMAGE_SIZE );
		if ( is_array( $size ) && ! empty( $size['file'] ) ) {
			$path = path_join( dirname( $original ), wp_basename( $size['file'] ) );
		}

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$bytes = filesize( $path );
		if ( false === $bytes || $bytes > self::IMAGE_MAX_BYTES ) {
			return null;
		}
		$mime = wp_get_image_mime( $path );
		if ( ! in_array( $mime, self::IMAGE_TYPES, true ) ) {
			return null;
		}

		return array(
			'path' => $path,
			'mime' => $mime,
		);
	}

	/**
	 * Takes the publishing lock of an entry. `INSERT IGNORE` is atomic: only
	 * one request can create the row. A lock older than LOCK_TTL is taken over
	 * once.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function acquire_lock( $post_id ) {
		global $wpdb;
		$name = self::LOCK_PREFIX . (int) $post_id;

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; add_option() and transients are not atomic.
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
					$name,
					(string) time()
				)
			);
			if ( 1 === $inserted ) {
				return true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the lock row itself, not a cached option.
			$since = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( null !== $since && time() - (int) $since <= self::LOCK_TTL ) {
				return false;
			}
			self::release_lock( $post_id );
		}
		return false;
	}

	/**
	 * Releases the publishing lock of an entry.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function release_lock( $post_id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK_PREFIX . (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock row.
	}

	/**
	 * Whether the entry already has a link (any non-empty value).
	 *
	 * @param \WP_Post $post Entry.
	 * @return bool
	 */
	private static function has_link( $post ) {
		$value = get_post_meta( $post->ID, Post_Meta::KEY, true );
		return is_string( $value ) && '' !== trim( $value );
	}

	/**
	 * Primary subtag of the site language (`gl_ES` → `gl`), or '' if it is
	 * not a two- or three-letter code.
	 *
	 * @return string
	 */
	private static function site_language() {
		$parts = explode( '_', (string) get_locale() );
		$lang  = strtolower( $parts[0] );
		return preg_match( '/^[a-z]{2,3}\z/', $lang ) ? $lang : '';
	}

	/**
	 * Current UTC time with milliseconds, e.g. 2026-10-16T09:30:00.123Z.
	 *
	 * @return string
	 */
	private static function now_iso() {
		$now = \DateTime::createFromFormat( 'U.u', sprintf( '%.6F', microtime( true ) ), new \DateTimeZone( 'UTC' ) );
		return $now->format( 'Y-m-d\TH:i:s.v\Z' );
	}

	/**
	 * Plain text: without tags, with entities decoded and spaces collapsed.
	 *
	 * @param string $value HTML or text.
	 * @return string
	 */
	private static function plain_text( $value ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * 400 error for a failed precondition.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private static function error_invalid( $code, $message ) {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}

	/**
	 * 404 error for a missing entry.
	 *
	 * @return \WP_Error
	 */
	private static function error_not_found() {
		return new \WP_Error( 'atproto_comments_post_not_found', __( 'The entry does not exist.', 'atproto-comments' ), array( 'status' => 404 ) );
	}

	/**
	 * 409 error while another request publishes or deletes the post of the
	 * same entry.
	 *
	 * @return \WP_Error
	 */
	private static function error_locked() {
		return new \WP_Error(
			'atproto_comments_locked',
			__( 'There is already an operation in progress with the post of this entry. Wait a moment and reload the editor.', 'atproto-comments' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * 400 error for an entry without a link to delete.
	 *
	 * @return \WP_Error
	 */
	private static function error_not_linked() {
		return self::error_invalid( 'atproto_comments_not_linked', __( 'This entry is not linked to an AT Protocol post.', 'atproto-comments' ) );
	}

	/**
	 * Error for a deletion whose result is unknown. Trying again is safe.
	 *
	 * @return \WP_Error
	 */
	private static function error_delete_unconfirmed() {
		return new \WP_Error(
			'atproto_comments_delete_unconfirmed',
			__( 'It could not be confirmed that the post was deleted. Try again.', 'atproto-comments' ),
			array( 'status' => 502 )
		);
	}

	/**
	 * 409 error for an entry that is already linked.
	 *
	 * @return \WP_Error
	 */
	private static function error_linked() {
		return new \WP_Error(
			'atproto_comments_already_linked',
			__( 'This entry is already linked to an AT Protocol post. Unlink it first to publish a new one.', 'atproto-comments' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Error for a createRecord whose result is unknown.
	 *
	 * @return \WP_Error
	 */
	private static function error_uncertain() {
		return new \WP_Error(
			'atproto_comments_uncertain',
			__( 'It is not known whether the post was created. Check the account\'s profile before trying again; if the post is there, paste its link in the thread link field.', 'atproto-comments' ),
			array( 'status' => 502 )
		);
	}
}
