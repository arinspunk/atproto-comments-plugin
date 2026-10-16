<?php
/**
 * The site's AT Protocol account: stored credentials, settings section and
 * connection test.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Account stored in the `atproto_comments_account` option (not autoloaded),
 * with the app password encrypted.
 */
final class Account {

	const OPTION = 'atproto_comments_account';

	const SECTION = 'atproto_comments_account';

	const FORGET_FORM = 'atproto-comments-forget-account';

	const REST_NAMESPACE = 'atproto-comments/v1';

	/**
	 * Empty account.
	 *
	 * @return array{identifier: string, password: string}
	 */
	public static function defaults() {
		return array(
			'identifier' => '',
			'password'   => '',
		);
	}

	/**
	 * Stored account: identifier and encrypted password.
	 *
	 * @return array{identifier: string, password: string}
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$account = wp_parse_args( $saved, self::defaults() );
		return array(
			'identifier' => is_string( $account['identifier'] ) ? $account['identifier'] : '',
			'password'   => is_string( $account['password'] ) ? $account['password'] : '',
		);
	}

	/**
	 * Whether the account has an identifier and a password that decrypts.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$account = self::get();
		if ( ! self::is_valid_identifier( $account['identifier'] ) ) {
			return false;
		}
		$password   = Crypto::decrypt( $account['password'] );
		$configured = null !== $password && '' !== $password;
		Crypto::wipe( $password );
		return $configured;
	}

	/**
	 * Whether a password is stored but cannot be decrypted (e.g. AUTH_KEY
	 * changed).
	 *
	 * @return bool
	 */
	public static function is_password_unreadable() {
		$account = self::get();
		if ( '' === $account['password'] ) {
			return false;
		}
		$password   = Crypto::decrypt( $account['password'] );
		$unreadable = null === $password;
		Crypto::wipe( $password );
		return $unreadable;
	}

	/**
	 * Registers the setting in the same group as the display settings, with
	 * its own sanitize callback.
	 */
	public static function register() {
		register_setting(
			Settings::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Keeps the option out of autoload. options.php creates it with autoload
	 * on its first save, and register_setting() cannot change that.
	 */
	public static function disable_autoload() {
		wp_set_option_autoload( self::OPTION, false );
	}

	/**
	 * Sanitizes the submitted account.
	 *
	 * - `forget` empties the account.
	 * - An invalid identifier adds an error and keeps the stored account.
	 * - An empty `app_password` keeps the stored password; a new one is
	 *   encrypted.
	 *
	 * On the first save WordPress runs this twice, the second time with the
	 * already sanitized value, so a `password` that is already encrypted is
	 * kept as it is.
	 *
	 * @param mixed $input Submitted value.
	 * @return array{identifier: string, password: string}
	 */
	public static function sanitize( $input ) {
		$previous = self::get();
		if ( ! is_array( $input ) ) {
			return $previous;
		}

		if ( ! empty( $input['forget'] ) ) {
			return self::defaults();
		}

		$identifier = isset( $input['identifier'] ) && is_string( $input['identifier'] )
			? self::normalize_identifier( $input['identifier'] )
			: $previous['identifier'];
		$new        = isset( $input['app_password'] ) && is_string( $input['app_password'] ) ? trim( $input['app_password'] ) : '';

		if ( '' === $identifier && '' === $new && '' === $previous['identifier'] ) {
			return $previous;
		}
		if ( ! self::is_valid_identifier( $identifier ) ) {
			Crypto::wipe( $new );
			self::add_error( 'identifier', __( 'The AT Protocol account must be a handle (like example.bsky.social) or a DID (did:…). The saved account has not changed.', 'atproto-comments' ) );
			return $previous;
		}

		if ( '' !== $new ) {
			$password = Crypto::encrypt( $new );
		} elseif ( isset( $input['password'] ) && is_string( $input['password'] ) && 0 === strpos( $input['password'], Crypto::PREFIX ) ) {
			$password = $input['password'];
		} else {
			$password = $previous['password'];
		}
		Crypto::wipe( $new );

		return array(
			'identifier' => $identifier,
			'password'   => $password,
		);
	}

	/**
	 * Lowercases an identifier and removes a leading `@`. The `%3A` of a
	 * did:web port keeps its case.
	 *
	 * @param string $value Identifier.
	 * @return string
	 */
	private static function normalize_identifier( $value ) {
		$value = strtolower( ltrim( trim( $value ), '@' ) );
		return str_replace( '%3a', '%3A', $value );
	}

	/**
	 * Whether an identifier is a handle or a supported DID.
	 *
	 * @param string $value Identifier.
	 * @return bool
	 */
	private static function is_valid_identifier( $value ) {
		return Identity::is_handle( $value ) || Identity::is_did( $value );
	}

	/**
	 * Adds a settings error once (the sanitize callback can run twice).
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 */
	private static function add_error( $code, $message ) {
		foreach ( get_settings_errors( self::OPTION ) as $error ) {
			if ( $error['code'] === $code ) {
				return;
			}
		}
		add_settings_error( self::OPTION, $code, $message, 'error' );
	}

	/**
	 * Registers the account section of the settings page.
	 */
	public static function add_fields() {
		add_settings_section(
			self::SECTION,
			__( 'AT Protocol account', 'atproto-comments' ),
			array( __CLASS__, 'render_section' ),
			Settings::PAGE
		);
		add_settings_field(
			'atproto_comments_account_identifier',
			__( 'Handle or DID', 'atproto-comments' ),
			array( __CLASS__, 'field_identifier' ),
			Settings::PAGE,
			self::SECTION,
			array( 'label_for' => 'atproto-comments-account-identifier' )
		);
		add_settings_field(
			'atproto_comments_account_password',
			__( 'App password', 'atproto-comments' ),
			array( __CLASS__, 'field_password' ),
			Settings::PAGE,
			self::SECTION,
			array( 'label_for' => 'atproto-comments-account-password' )
		);
		add_settings_field(
			'atproto_comments_account_test',
			__( 'Connection', 'atproto-comments' ),
			array( __CLASS__, 'field_test' ),
			Settings::PAGE,
			self::SECTION
		);
	}

	/**
	 * Section introduction.
	 */
	public static function render_section() {
		?>
		<p>
			<?php esc_html_e( 'Account used to publish the anchor post of an entry from the editor. There is one account for the whole site.', 'atproto-comments' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Use an app password, not the account\'s main password. An app password can only be used by apps like this one and can be revoked at any time without changing the main password. In Bluesky, create one in Settings → Privacy and security → App passwords.', 'atproto-comments' ); ?>
		</p>
		<p>
			<?php
			printf(
				/* translators: %s: name of the filter, atproto_comments_publish_capability. */
				esc_html__( 'Only editors and administrators can publish to the site account. Developers can change this with the %s filter.', 'atproto-comments' ),
				'<code>atproto_comments_publish_capability</code>'
			);
			?>
		</p>
		<?php
		if ( self::is_password_unreadable() ) {
			?>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'The saved app password can no longer be decrypted, probably because the site\'s security keys changed. Enter the app password again.', 'atproto-comments' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Identifier field.
	 */
	public static function field_identifier() {
		$account = self::get();
		?>
		<input type="text" class="regular-text code" id="atproto-comments-account-identifier"
			name="<?php echo esc_attr( self::OPTION ); ?>[identifier]"
			value="<?php echo esc_attr( $account['identifier'] ); ?>"
			autocomplete="username" spellcheck="false"
			placeholder="example.bsky.social"
			aria-describedby="atproto-comments-account-identifier-description" />
		<p class="description" id="atproto-comments-account-identifier-description">
			<?php esc_html_e( 'The account handle without @, or its DID.', 'atproto-comments' ); ?>
		</p>
		<?php
	}

	/**
	 * App password field. Always empty: the saved password never reaches the
	 * browser.
	 */
	public static function field_password() {
		$account = self::get();
		$saved   = '' !== $account['password'] && ! self::is_password_unreadable();
		?>
		<input type="password" class="regular-text" id="atproto-comments-account-password"
			name="<?php echo esc_attr( self::OPTION ); ?>[app_password]"
			value="" autocomplete="new-password" spellcheck="false"
			aria-describedby="atproto-comments-account-password-description" />
		<p class="description" id="atproto-comments-account-password-description">
			<?php
			if ( $saved ) {
				esc_html_e( 'An app password is saved. Leave the field empty to keep it.', 'atproto-comments' );
			} else {
				esc_html_e( 'No app password is saved.', 'atproto-comments' );
			}
			?>
		</p>
		<?php if ( '' !== $account['identifier'] || '' !== $account['password'] ) : ?>
			<p>
				<button type="submit" class="button button-link-delete" form="<?php echo esc_attr( self::FORGET_FORM ); ?>">
					<?php esc_html_e( 'Forget account', 'atproto-comments' ); ?>
				</button>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Connection test button and its live region.
	 */
	public static function field_test() {
		?>
		<button type="button" class="button" id="atproto-comments-account-test">
			<?php esc_html_e( 'Test connection', 'atproto-comments' ); ?>
		</button>
		<p class="description"><?php esc_html_e( 'Checks the saved account: resolves it and signs in to its server.', 'atproto-comments' ); ?></p>
		<div id="atproto-comments-account-test-result" aria-live="polite"></div>
		<?php
	}

	/**
	 * Separate form used by the "Forget account" button. It is outside the
	 * settings form so that pressing Enter in a field never forgets the
	 * account. options.php gets no display settings from it, and their
	 * sanitize callback keeps the saved values.
	 */
	public static function render_forget_form() {
		?>
		<form id="<?php echo esc_attr( self::FORGET_FORM ); ?>" action="options.php" method="post">
			<?php settings_fields( Settings::GROUP ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[forget]" value="1" />
		</form>
		<?php
	}

	/**
	 * Enqueues the connection test script on the settings page.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . Settings::PAGE !== $hook_suffix ) {
			return;
		}
		$asset_file = ATPROTO_COMMENTS_DIR . 'build/settings.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'atproto-comments-settings',
			ATPROTO_COMMENTS_URL . 'build/settings.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'atproto-comments-settings', 'atproto-comments' );
	}

	/**
	 * Registers the REST route of the connection test.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/account/test',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_test' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Tests the saved account: resolves it and opens a session.
	 *
	 * @return \WP_REST_Response|\WP_Error `{ handle, did, pds }`.
	 */
	public static function rest_test() {
		if ( ! self::is_configured() ) {
			return self::error_not_configured();
		}

		$deadline = new Deadline();
		$account  = Identity::resolve_account( self::get()['identifier'], $deadline );
		if ( is_wp_error( $account ) ) {
			return $account;
		}

		$password = Crypto::decrypt( self::get()['password'] );
		$session  = Xrpc::create_session( $account['pds'], $account['did'], (string) $password, $deadline );
		Crypto::wipe( $password );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		return new \WP_REST_Response(
			array(
				'handle' => $account['handle'],
				'did'    => $account['did'],
				'pds'    => $account['pds'],
			)
		);
	}

	/**
	 * Error for an account that is missing or cannot be decrypted.
	 *
	 * @return \WP_Error
	 */
	public static function error_not_configured() {
		return new \WP_Error(
			'atproto_comments_account_missing',
			__( 'The site has no AT Protocol account configured. Save the handle and an app password in the plugin settings first.', 'atproto-comments' ),
			array( 'status' => 400 )
		);
	}
}
