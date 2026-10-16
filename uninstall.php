<?php
/**
 * Uninstall: deletes the settings, the AT Protocol account, the publishing
 * locks and the cached handles on every site. Post links
 * (`_atproto_thread_uri`) are kept.
 *
 * @package AtprotoComments
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Deletes the plugin data of the current site.
$atproto_comments_uninstall_site = static function () {
	global $wpdb;

	delete_option( 'atproto_comments_settings' );
	delete_option( 'atproto_comments_account' );

	// Locks and cached handles have variable names. With a persistent object
	// cache the handle transients are not in the table and expire on their own.
	foreach ( array( 'atproto_comments_lock_', '_transient_atproto_comments_handle_', '_transient_timeout_atproto_comments_handle_' ) as $atproto_comments_prefix ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off cleanup of rows with variable names.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $atproto_comments_prefix ) . '%'
			)
		);
	}
	wp_cache_delete( 'alloptions', 'options' );
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $atproto_comments_site_id ) {
		switch_to_blog( $atproto_comments_site_id );
		$atproto_comments_uninstall_site();
		restore_current_blog();
	}
} else {
	$atproto_comments_uninstall_site();
}
