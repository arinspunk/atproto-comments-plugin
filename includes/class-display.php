<?php
/**
 * Frontend: when and where <atproto-comments> is printed, and its assets.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether to render the component and inserts it.
 */
final class Display {

	/**
	 * IDs of the posts already rendered in this request.
	 *
	 * @var array<int, true>
	 */
	private static $rendered = array();

	/**
	 * Whether the component should be shown for a post: the singular view of
	 * the queried post, of an active type, with a valid link.
	 *
	 * @param int|\WP_Post|null $post Post. Defaults to the current post.
	 * @return bool
	 */
	public static function should_render( $post = null ) {
		$post = get_post( $post );
		if ( ! $post || ! is_singular() || is_feed() ) {
			return false;
		}
		if ( (int) get_queried_object_id() !== (int) $post->ID ) {
			return false;
		}
		if ( ! Settings::is_active_type( $post->post_type ) || post_password_required( $post ) ) {
			return false;
		}
		return '' !== Post_Meta::get( $post );
	}

	/**
	 * HTML of the component, once per post and request. Returns '' if the
	 * post should not show it or it was already printed.
	 *
	 * @param int|\WP_Post|null $post Post. Defaults to the current post.
	 * @return string
	 */
	public static function render( $post = null ) {
		$post = get_post( $post );
		if ( ! $post || ! self::should_render( $post ) || isset( self::$rendered[ $post->ID ] ) ) {
			return '';
		}
		self::$rendered[ $post->ID ] = true;

		$settings   = Settings::get();
		$attributes = array(
			'thread-uri' => Post_Meta::get( $post ),
			'appview'    => $settings['appview'],
			'max-depth'  => (string) $settings['max_depth'],
			'lang'       => self::lang(),
		);
		$strings    = UI_Strings::translated();
		if ( $strings ) {
			$attributes['strings'] = wp_json_encode( $strings );
		}

		$html = '<atproto-comments';
		foreach ( $attributes as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		return $html . '></atproto-comments>';
	}

	/**
	 * Site locale as a BCP 47 tag: es_ES -> es-ES.
	 *
	 * @return string
	 */
	public static function lang() {
		return str_replace( '_', '-', determine_locale() );
	}

	/**
	 * Forgets which posts were rendered. For tests.
	 */
	public static function reset() {
		self::$rendered = array();
	}

	/**
	 * Enqueues the component module and stylesheet, only where it is shown.
	 */
	public static function enqueue_assets() {
		if ( ! is_singular() || ! self::should_render( get_queried_object() ) ) {
			return;
		}

		$base    = ATPROTO_COMMENTS_URL . 'assets/vendor/atproto-comments/';
		$version = self::component_version();

		wp_enqueue_script_module( 'atproto-comments', $base . 'index.js', array(), $version );
		wp_enqueue_style( 'atproto-comments', $base . 'atproto-comments.css', array(), $version );
	}

	/**
	 * Version of the bundled component, written by `npm run vendor`.
	 *
	 * @return string
	 */
	private static function component_version() {
		$file = ATPROTO_COMMENTS_DIR . 'assets/vendor/atproto-comments/version.php';
		if ( is_readable( $file ) ) {
			$version = include $file;
			if ( is_string( $version ) && '' !== $version ) {
				return $version;
			}
		}
		return ATPROTO_COMMENTS_VERSION;
	}

	/**
	 * Current position setting.
	 *
	 * @return string 'replace' or 'after_content'.
	 */
	private static function position() {
		return Settings::get()['position'];
	}

	/**
	 * `the_content`: adds the component after the content of the queried
	 * post. In "replace" mode only when comments are closed and there are
	 * none, because many themes then skip the comments template.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( ! in_the_loop() || ! is_main_query() || is_feed() || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || (int) get_queried_object_id() !== (int) $post->ID ) {
			return $content;
		}
		if ( 'replace' === self::position() && ( comments_open( $post ) || get_comments_number( $post ) ) ) {
			return $content;
		}
		return $content . self::render( $post );
	}

	/**
	 * `comments_template`: in "replace" mode, swaps the theme's comments
	 * template (list and form) for one that prints the component.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public static function filter_comments_template( $template ) {
		if ( 'replace' === self::position() && self::should_render() ) {
			return ATPROTO_COMMENTS_DIR . 'templates/comments.php';
		}
		return $template;
	}

	/**
	 * `render_block_core/comments` and `render_block_core/post-comments`: in
	 * "replace" mode, the block output becomes the component. In legacy mode
	 * these blocks go through comments_template(), so the output may already
	 * contain it.
	 *
	 * @param string         $block_content Block HTML.
	 * @param array          $block         Parsed block.
	 * @param \WP_Block|null $instance      Block instance.
	 * @return string
	 */
	public static function filter_comments_block( $block_content, $block, $instance = null ) {
		$post = self::block_post( $instance );
		if ( 'replace' !== self::position() || ! self::should_render( $post ) ) {
			return $block_content;
		}
		if ( false !== strpos( (string) $block_content, '<atproto-comments' ) ) {
			return $block_content;
		}
		return self::render( $post );
	}

	/**
	 * `render_block_core/post-comments-form`: in "replace" mode, no native
	 * form on linked posts.
	 *
	 * @param string         $block_content Block HTML.
	 * @param array          $block         Parsed block.
	 * @param \WP_Block|null $instance      Block instance.
	 * @return string
	 */
	public static function filter_comments_form_block( $block_content, $block, $instance = null ) {
		if ( 'replace' === self::position() && self::should_render( self::block_post( $instance ) ) ) {
			return '';
		}
		return $block_content;
	}

	/**
	 * Post of a block from its context, or the current post.
	 *
	 * @param \WP_Block|null $instance Block instance.
	 * @return \WP_Post|null
	 */
	private static function block_post( $instance ) {
		if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
			return get_post( (int) $instance->context['postId'] );
		}
		return get_post();
	}
}
