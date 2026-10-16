<?php
/**
 * Hook registration.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin components to WordPress.
 */
final class Plugin {

	/**
	 * Registers all hooks.
	 */
	public static function init() {
		add_action( 'init', array( Settings::class, 'register' ) );
		add_action( 'admin_menu', array( Settings::class, 'add_page' ) );
		add_action( 'admin_init', array( Settings::class, 'add_fields' ) );

		add_action( 'init', array( Account::class, 'register' ) );
		add_action( 'add_option_' . Account::OPTION, array( Account::class, 'disable_autoload' ) );
		add_action( 'admin_init', array( Account::class, 'add_fields' ) );
		add_action( 'admin_enqueue_scripts', array( Account::class, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( Account::class, 'register_routes' ) );

		add_action( 'init', array( Post_Meta::class, 'register' ), 99 );
		add_action( 'add_meta_boxes', array( Post_Meta::class, 'add_metabox' ) );
		add_action( 'admin_notices', array( Post_Meta::class, 'admin_notices' ) );
		add_action( 'enqueue_block_editor_assets', array( Post_Meta::class, 'enqueue_editor_assets' ) );

		add_action( 'wp_enqueue_scripts', array( Display::class, 'enqueue_assets' ) );
		add_filter( 'the_content', array( Display::class, 'filter_content' ), 20 );
		add_filter( 'comments_template', array( Display::class, 'filter_comments_template' ) );
		add_filter( 'render_block_core/comments', array( Display::class, 'filter_comments_block' ), 10, 3 );
		add_filter( 'render_block_core/post-comments', array( Display::class, 'filter_comments_block' ), 10, 3 );
		add_filter( 'render_block_core/post-comments-form', array( Display::class, 'filter_comments_form_block' ), 10, 3 );
	}
}
