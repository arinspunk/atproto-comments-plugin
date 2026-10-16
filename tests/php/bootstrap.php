<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite provided by wp-env.
 *
 * @package AtprotoComments
 */

$atproto_comments_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $atproto_comments_tests_dir ) {
	$atproto_comments_tests_dir = '/wordpress-phpunit';
}

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Read by the WordPress test suite.

// `WP_TESTS_MULTISITE=1 vendor/bin/phpunit` runs the suite as multisite.
if ( getenv( 'WP_TESTS_MULTISITE' ) ) {
	define( 'WP_TESTS_MULTISITE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Read by the WordPress test suite.
}

require_once $atproto_comments_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/atproto-comments.php';
	}
);

require $atproto_comments_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/trait-network-mock.php';
require __DIR__ . '/class-publish-test-case.php';
