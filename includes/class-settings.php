<?php
/**
 * Plugin settings: defaults, getters, validation and the settings page.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings stored in the `atproto_comments_settings` option.
 */
final class Settings {

	const OPTION = 'atproto_comments_settings';

	const GROUP = 'atproto_comments';

	const PAGE = 'atproto-comments';

	const POSITIONS = array( 'replace', 'after_content' );

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'appview'    => 'https://public.api.bsky.app',
			'max_depth'  => 6,
			'position'   => 'replace',
			'post_types' => array( 'post' ),
		);
	}

	/**
	 * Current settings merged with the defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Post types where the plugin is active.
	 *
	 * @return string[]
	 */
	public static function active_post_types() {
		$types = self::get()['post_types'];
		return is_array( $types ) ? array_values( array_filter( $types, 'is_string' ) ) : array();
	}

	/**
	 * Whether the plugin is active for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_active_type( $post_type ) {
		return in_array( $post_type, self::active_post_types(), true );
	}

	/**
	 * Post types that can be selected: public ones except attachments.
	 *
	 * @return \WP_Post_Type[] Keyed by name.
	 */
	public static function available_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	/**
	 * Registers the setting. Runs on `init` so the sanitize callback also
	 * applies outside the settings screen.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
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
	 * Sanitizes the submitted settings. An invalid field keeps its previous
	 * value and adds a settings error; valid fields are saved.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$previous = self::get();
		if ( ! is_array( $input ) ) {
			return $previous;
		}

		$output = $previous;

		// AppView: an https URL, without trailing slashes.
		if ( isset( $input['appview'] ) ) {
			$appview = rtrim( trim( (string) $input['appview'] ), '/' );
			if ( self::is_valid_appview( $appview ) ) {
				$output['appview'] = $appview;
			} else {
				self::add_error( 'appview', __( 'The AppView must be a valid https URL. The previous value has been kept.', 'atproto-comments' ) );
			}
		}

		// Maximum depth: an integer between 1 and 20.
		if ( isset( $input['max_depth'] ) ) {
			$depth = is_int( $input['max_depth'] ) ? (string) $input['max_depth'] : trim( (string) $input['max_depth'] );
			if ( preg_match( '/^[0-9]{1,2}$/', $depth ) && (int) $depth >= 1 && (int) $depth <= 20 ) {
				$output['max_depth'] = (int) $depth;
			} else {
				self::add_error( 'max_depth', __( 'The maximum depth must be a whole number between 1 and 20. The previous value has been kept.', 'atproto-comments' ) );
			}
		}

		// Position: any other value is ignored.
		if ( isset( $input['position'] ) && in_array( $input['position'], self::POSITIONS, true ) ) {
			$output['position'] = $input['position'];
		}

		// Post types: missing means none checked. Only existing types are kept.
		$types                = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? $input['post_types'] : array();
		$available            = array_keys( self::available_post_types() );
		$output['post_types'] = array_values( array_intersect( $available, array_map( 'strval', $types ) ) );

		return array(
			'appview'    => (string) $output['appview'],
			'max_depth'  => (int) $output['max_depth'],
			'position'   => (string) $output['position'],
			'post_types' => $output['post_types'],
		);
	}

	/**
	 * Whether a value is a valid https URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_valid_appview( $url ) {
		if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'] )
			&& 'https' === strtolower( $parts['scheme'] );
	}

	/**
	 * Adds a settings error once. WordPress runs the sanitize callback twice
	 * the first time the option is saved (update_option, then add_option).
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
	 * Adds the page under Settings.
	 */
	public static function add_page() {
		add_options_page(
			__( 'AT Protocol Comments', 'atproto-comments' ),
			__( 'AT Protocol Comments', 'atproto-comments' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registers the settings section and fields.
	 */
	public static function add_fields() {
		add_settings_section( 'atproto_comments_main', '', '__return_false', self::PAGE );

		$fields = array(
			'appview'    => __( 'AppView', 'atproto-comments' ),
			'max_depth'  => __( 'Maximum depth', 'atproto-comments' ),
			'position'   => __( 'Position', 'atproto-comments' ),
			'post_types' => __( 'Content types', 'atproto-comments' ),
		);
		foreach ( $fields as $key => $label ) {
			$args = array();
			if ( in_array( $key, array( 'appview', 'max_depth' ), true ) ) {
				$args['label_for'] = 'atproto-comments-' . str_replace( '_', '-', $key );
			}
			add_settings_field(
				'atproto_comments_' . $key,
				$label,
				array( __CLASS__, 'field_' . $key ),
				self::PAGE,
				'atproto_comments_main',
				$args
			);
		}
	}

	/**
	 * Renders the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * AppView field.
	 */
	public static function field_appview() {
		$settings = self::get();
		?>
		<input type="url" class="regular-text code" id="atproto-comments-appview"
			name="<?php echo esc_attr( self::OPTION ); ?>[appview]"
			value="<?php echo esc_attr( $settings['appview'] ); ?>"
			aria-describedby="atproto-comments-appview-description" />
		<p class="description" id="atproto-comments-appview-description">
			<?php esc_html_e( 'Server that visitors\' browsers query to load the replies. Must use https.', 'atproto-comments' ); ?>
		</p>
		<?php
	}

	/**
	 * Maximum depth field.
	 */
	public static function field_max_depth() {
		$settings = self::get();
		?>
		<input type="number" class="small-text" id="atproto-comments-max-depth" min="1" max="20" step="1"
			name="<?php echo esc_attr( self::OPTION ); ?>[max_depth]"
			value="<?php echo esc_attr( (string) $settings['max_depth'] ); ?>"
			aria-describedby="atproto-comments-max-depth-description" />
		<p class="description" id="atproto-comments-max-depth-description">
			<?php esc_html_e( 'How many levels of nested replies to load (1–20). Deeper replies link to the full thread.', 'atproto-comments' ); ?>
		</p>
		<?php
	}

	/**
	 * Position field.
	 */
	public static function field_position() {
		$settings = self::get();
		$options  = array(
			'replace'       => __( 'Replace the native comments', 'atproto-comments' ),
			'after_content' => __( 'After the content, keeping the native comments', 'atproto-comments' ),
		);
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Position', 'atproto-comments' ); ?></legend>
			<?php foreach ( $options as $value => $label ) : ?>
				<label>
					<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[position]"
						value="<?php echo esc_attr( $value ); ?>" <?php checked( $settings['position'], $value ); ?> />
					<?php echo esc_html( $label ); ?>
				</label><br />
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Post types field.
	 */
	public static function field_post_types() {
		$active = self::active_post_types();
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'atproto-comments' ); ?></legend>
			<?php foreach ( self::available_post_types() as $name => $type ) : ?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[post_types][]"
						value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $active, true ) ); ?> />
					<?php echo esc_html( $type->labels->name ); ?>
				</label><br />
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Content types where posts can be linked to an AT Protocol thread.', 'atproto-comments' ); ?></p>
		</fieldset>
		<?php
	}
}
