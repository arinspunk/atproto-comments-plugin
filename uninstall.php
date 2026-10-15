<?php
/**
 * Uninstall: deletes the settings on every site. Post links
 * (`_atproto_thread_uri`) are kept.
 *
 * @package AtprotoComments
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $atproto_comments_site_id ) {
		switch_to_blog( $atproto_comments_site_id );
		delete_option( 'atproto_comments_settings' );
		restore_current_blog();
	}
} else {
	delete_option( 'atproto_comments_settings' );
}
