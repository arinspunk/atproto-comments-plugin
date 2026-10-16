<?php
/**
 * Plugin Name:       ATproto Comments
 * Plugin URI:        https://github.com/arinspunk/atproto-comments-plugin
 * Description:       Shows replies to an AT Protocol (Bluesky) post as the comments of a post.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Xúlio Zé
 * Author URI:        https://xulioze.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       atproto-comments
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATPROTO_COMMENTS_VERSION', '0.1.0' );
define( 'ATPROTO_COMMENTS_FILE', __FILE__ );
define( 'ATPROTO_COMMENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATPROTO_COMMENTS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Autoload: AtprotoComments\Thread_Uri -> includes/class-thread-uri.php.
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$name = strtolower( str_replace( '_', '-', substr( $class_name, strlen( $prefix ) ) ) );
		$file = ATPROTO_COMMENTS_DIR . 'includes/class-' . $name . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

Plugin::init();
