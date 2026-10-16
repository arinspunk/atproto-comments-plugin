<?php
/**
 * The `_atproto_thread_uri` post meta and the classic editor metabox.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the thread link meta for the active post types.
 */
final class Post_Meta {

	const KEY = '_atproto_thread_uri';

	const NONCE = 'atproto_comments_thread_nonce';

	const FIELD = 'atproto_comments_thread_uri';

	/**
	 * Registers the meta for every active post type. Runs late on `init`,
	 * after post types are registered.
	 */
	public static function register() {
		foreach ( Settings::active_post_types() as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}

			// The meta only shows in REST for types that support custom fields.
			if ( ! post_type_supports( $type, 'custom-fields' ) ) {
				add_post_type_support( $type, 'custom-fields' );
			}

			register_post_meta(
				$type,
				self::KEY,
				array(
					'single'            => true,
					'type'              => 'string',
					'default'           => '',
					'show_in_rest'      => array(
						'schema' => array(
							'type'    => 'string',
							'pattern' => Thread_Uri::PATTERN,
						),
					),
					'auth_callback'     => array( __CLASS__, 'can_edit' ),
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				)
			);

			add_action( "save_post_{$type}", array( __CLASS__, 'save_metabox' ), 10, 2 );
		}
	}

	/**
	 * Adds the classic editor metabox to active post types. Hidden in the
	 * block editor, which has its own panel.
	 *
	 * @param string $post_type Post type of the screen.
	 */
	public static function add_metabox( $post_type ) {
		if ( ! Settings::is_active_type( $post_type ) ) {
			return;
		}
		add_meta_box(
			'atproto-comments-thread',
			__( 'AT Protocol comments', 'atproto-comments' ),
			array( __CLASS__, 'render_metabox' ),
			$post_type,
			'side',
			'default',
			array( '__back_compat_meta_box' => true )
		);
	}

	/**
	 * Renders the metabox.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render_metabox( $post ) {
		wp_nonce_field( 'atproto_comments_save_thread', self::NONCE );
		$value = get_post_meta( $post->ID, self::KEY, true );
		?>
		<p>
			<label for="atproto-comments-thread-uri"><?php esc_html_e( 'Thread link', 'atproto-comments' ); ?></label>
			<input type="text" class="widefat code" id="atproto-comments-thread-uri"
				name="<?php echo esc_attr( self::FIELD ); ?>"
				value="<?php echo esc_attr( is_string( $value ) ? $value : '' ); ?>"
				placeholder="at://did:plc:…/app.bsky.feed.post/…"
				aria-describedby="atproto-comments-thread-uri-help" />
		</p>
		<p class="description" id="atproto-comments-thread-uri-help">
			<?php esc_html_e( 'Paste the URL of the post or its at:// URI. Leave empty to unlink.', 'atproto-comments' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueues the block editor panel for active post types.
	 */
	public static function enqueue_editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! $screen->post_type || ! Settings::is_active_type( $screen->post_type ) ) {
			return;
		}

		$asset_file = ATPROTO_COMMENTS_DIR . 'build/editor.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'atproto-comments-editor',
			ATPROTO_COMMENTS_URL . 'build/editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'atproto-comments-editor', 'atproto-comments' );
	}

	/**
	 * Saves the metabox value. Does nothing without the nonce (REST saves),
	 * on autosaves, on revisions or without `edit_post`.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ], $_POST[ self::FIELD ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), 'atproto_comments_save_thread' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! Settings::is_active_type( $post->post_type ) ) {
			return;
		}

		// Thread_Uri::normalize() only accepts a strict character set. Not
		// sanitize_text_field(): it strips the %3A of did:web ports.
		$result = Thread_Link::resolve( (string) wp_unslash( $_POST[ self::FIELD ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! $result['ok'] ) {
			set_transient( self::notice_key(), $result['error'], MINUTE_IN_SECONDS );
			return;
		}

		if ( '' === $result['uri'] ) {
			delete_post_meta( $post_id, self::KEY );
		} else {
			update_post_meta( $post_id, self::KEY, $result['uri'] );
		}
	}

	/**
	 * Shows and clears the pending error of the current user.
	 */
	public static function admin_notices() {
		$key   = self::notice_key();
		$error = get_transient( $key );
		if ( false === $error ) {
			return;
		}
		delete_transient( $key );
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( self::error_message( $error ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Message for a link error.
	 *
	 * @param string $error 'handle_unresolved' or 'format'.
	 * @return string
	 */
	public static function error_message( $error ) {
		if ( 'handle_unresolved' === $error ) {
			return __( 'The handle in the AT Protocol link could not be resolved. Check it, or paste the post URL or the at:// URI with the DID (did:…). The previous link has been kept.', 'atproto-comments' );
		}
		return __( 'The AT Protocol link is not valid. Paste the URL of a post, like https://bsky.app/profile/…/post/…, or its at:// URI. The previous link has been kept.', 'atproto-comments' );
	}

	/**
	 * Transient key for the current user's notice.
	 *
	 * @return string
	 */
	private static function notice_key() {
		return 'atproto_comments_notice_' . get_current_user_id();
	}

	/**
	 * Only users who can edit the post can write the meta.
	 *
	 * @param bool   $allowed  Whether allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Returns the canonical form of a valid value. An invalid value is only
	 * trimmed, never emptied: the callback cannot see the previous value, and
	 * emptying would silently unlink the post. Each channel rejects invalid
	 * values before writing, and the frontend validates again on read.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$result = Thread_Uri::normalize( is_scalar( $value ) ? (string) $value : '' );
		return $result['ok'] ? $result['uri'] : trim( (string) $value );
	}

	/**
	 * The canonical thread URI of a post, or '' if it has none or it is invalid.
	 *
	 * @param int|\WP_Post $post Post.
	 * @return string
	 */
	public static function get( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}
		$value = get_post_meta( $post->ID, self::KEY, true );
		return Thread_Uri::is_canonical( $value ) ? $value : '';
	}
}
