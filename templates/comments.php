<?php
/**
 * Replaces the theme's comments template on linked posts ("replace" mode).
 *
 * @package AtprotoComments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Escaped in Display::render().
echo AtprotoComments\Display::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
