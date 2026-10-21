<?php
/**
 * Tests for Display.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Display;
use AtprotoComments\Post_Meta;
use AtprotoComments\Settings;

/**
 * Render conditions, attributes and insertion points.
 */
class Display_Test extends WP_UnitTestCase {

	const URI = 'at://did:plc:abc234567abcdefghijklmno/app.bsky.feed.post/3mwso7oovnp2u';

	/**
	 * Linked post ID.
	 *
	 * @var int
	 */
	private $post_id;

	public function set_up() {
		parent::set_up();
		Display::reset();
		delete_option( Settings::OPTION );
		$this->post_id = self::factory()->post->create( array( 'post_content' => 'Body' ) );
		update_post_meta( $this->post_id, Post_Meta::KEY, self::URI );
	}

	public function tear_down() {
		Display::reset();
		parent::tear_down();
	}

	/**
	 * Saves one setting.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	private function set_setting( $key, $value ) {
		update_option( Settings::OPTION, array_merge( Settings::get(), array( $key => $value ) ) );
	}

	/**
	 * Opens the single view of a post and enters the loop.
	 *
	 * @param int $post_id Post ID.
	 */
	private function view( $post_id ) {
		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['wp_query']->the_post();
	}

	/**
	 * Closes comments of the linked post.
	 */
	private function close_comments() {
		wp_update_post(
			array(
				'ID'             => $this->post_id,
				'comment_status' => 'closed',
			)
		);
	}

	// Render conditions and attributes.

	public function test_renders_on_linked_single_post() {
		$this->view( $this->post_id );
		$this->assertTrue( Display::should_render() );
		$html = Display::render();
		$this->assertStringStartsWith( '<atproto-comments ', $html );
		$this->assertStringContainsString( 'thread-uri="' . self::URI . '"', $html );
		$this->assertStringContainsString( 'appview="https://public.api.bsky.app"', $html );
		$this->assertStringContainsString( 'max-depth="6"', $html );
		$this->assertStringContainsString( 'lang="en-US"', $html );
	}

	public function test_second_render_is_empty() {
		$this->view( $this->post_id );
		$this->assertNotSame( '', Display::render() );
		$this->assertSame( '', Display::render() );
	}

	public function test_not_rendered_without_link() {
		$other = self::factory()->post->create();
		$this->view( $other );
		$this->assertFalse( Display::should_render() );
		$this->assertSame( '', Display::render() );
	}

	public function test_not_rendered_with_non_canonical_meta() {
		// The sanitize callback keeps invalid values as they are.
		update_post_meta( $this->post_id, Post_Meta::KEY, 'at://usoritual.com/app.bsky.feed.post/3mwso7oovnp2u' );
		$this->assertSame( 'at://usoritual.com/app.bsky.feed.post/3mwso7oovnp2u', get_post_meta( $this->post_id, Post_Meta::KEY, true ) );
		$this->view( $this->post_id );
		$this->assertFalse( Display::should_render() );
	}

	public function test_not_rendered_for_inactive_type() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, Post_Meta::KEY, self::URI );
		$this->view( $page );
		$this->assertFalse( Display::should_render() );

		$this->set_setting( 'post_types', array( 'post', 'page' ) );
		$this->assertTrue( Display::should_render() );
	}

	public function test_not_rendered_in_archives_or_feeds() {
		$this->go_to( home_url( '/' ) );
		$this->assertFalse( Display::should_render( $this->post_id ) );

		$category = self::factory()->category->create();
		wp_set_post_categories( $this->post_id, array( $category ) );
		$this->go_to( get_category_link( $category ) );
		$this->assertFalse( Display::should_render( $this->post_id ) );

		$this->go_to( get_feed_link() );
		$this->assertFalse( Display::should_render( $this->post_id ) );
	}

	public function test_not_rendered_for_other_post_on_single_view() {
		$other = self::factory()->post->create();
		update_post_meta( $other, Post_Meta::KEY, self::URI );
		$this->view( $this->post_id );
		$this->assertFalse( Display::should_render( $other ) );
	}

	public function test_attributes_from_settings_are_escaped() {
		$this->set_setting( 'appview', 'https://appview.example/a"b' );
		$this->set_setting( 'max_depth', '12' );
		$this->view( $this->post_id );
		$html = Display::render();
		$this->assertStringContainsString( 'max-depth="12"', $html );
		$this->assertStringNotContainsString( 'a"b', $html );
	}

	/**
	 * Locales.
	 *
	 * @return array
	 */
	public function locales() {
		return array(
			'es_ES'        => array( 'es_ES', 'es-ES' ),
			'de_DE_formal' => array( 'de_DE_formal', 'de-DE-formal' ),
			'pt_BR'        => array( 'pt_BR', 'pt-BR' ),
			'en_US'        => array( 'en_US', 'en-US' ),
		);
	}

	/**
	 * The lang attribute is the locale in BCP 47 form.
	 *
	 * @dataProvider locales
	 *
	 * @param string $locale   WordPress locale.
	 * @param string $expected lang attribute.
	 */
	public function test_lang( $locale, $expected ) {
		$filter = static function () use ( $locale ) {
			return $locale;
		};
		add_filter( 'determine_locale', $filter );
		$this->view( $this->post_id );
		$this->assertStringContainsString( 'lang="' . $expected . '"', Display::render() );
		remove_filter( 'determine_locale', $filter );
	}

	public function test_no_strings_attribute_without_translations() {
		$this->view( $this->post_id );
		$this->assertStringNotContainsString( 'strings=', Display::render() );
	}

	public function test_strings_attribute_with_translations() {
		$filter = static function ( $translation, $text, $domain ) {
			return 'parolas' === $domain && 'Reply to {name}' === $text ? 'Responder a {name}' : $translation;
		};
		add_filter( 'gettext', $filter, 10, 3 );
		$this->view( $this->post_id );
		$html = Display::render();
		remove_filter( 'gettext', $filter, 10 );

		$this->assertSame( 1, preg_match( '/strings="([^"]*)"/', $html, $m ) );
		$this->assertSame( array( 'replyTo' => 'Responder a {name}' ), json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true ) );
	}

	// the_content.

	public function test_after_content_mode_appends_component() {
		$this->set_setting( 'position', 'after_content' );
		$this->view( $this->post_id );
		$out = apply_filters( 'the_content', 'Body' );
		$this->assertStringContainsString( 'Body', $out );
		$this->assertSame( 1, substr_count( $out, '<atproto-comments' ) );
		$this->assertSame( 0, substr_count( apply_filters( 'the_content', 'Body' ), '<atproto-comments' ) );
	}

	public function test_replace_mode_with_open_comments_leaves_content() {
		$this->view( $this->post_id );
		$this->assertStringNotContainsString( '<atproto-comments', apply_filters( 'the_content', 'Body' ) );
	}

	public function test_replace_mode_with_closed_comments_appends_component() {
		$this->close_comments();
		$this->view( $this->post_id );
		$this->assertStringContainsString( '<atproto-comments', apply_filters( 'the_content', 'Body' ) );
	}

	public function test_replace_mode_with_closed_comments_and_approved_comments_leaves_content() {
		self::factory()->comment->create( array( 'comment_post_ID' => $this->post_id ) );
		$this->close_comments();
		$this->view( $this->post_id );
		$this->assertStringNotContainsString( '<atproto-comments', apply_filters( 'the_content', 'Body' ) );
	}

	public function test_content_outside_the_loop_is_untouched() {
		$this->set_setting( 'position', 'after_content' );
		$this->go_to( get_permalink( $this->post_id ) );
		$this->assertStringNotContainsString( '<atproto-comments', apply_filters( 'the_content', 'Body' ) );
	}

	public function test_excerpt_does_not_consume_the_flag() {
		$this->set_setting( 'position', 'after_content' );
		$this->view( $this->post_id );
		$excerpt = get_the_excerpt();
		$this->assertStringNotContainsString( 'atproto', $excerpt );
		$this->assertStringContainsString( '<atproto-comments', apply_filters( 'the_content', 'Body' ) );
	}

	// comments_template.

	public function test_comments_template_replaced_in_replace_mode() {
		$this->view( $this->post_id );
		$template = apply_filters( 'comments_template', '/theme/comments.php' );
		$this->assertSame( ATPROTO_COMMENTS_DIR . 'templates/comments.php', $template );

		ob_start();
		include $template;
		$this->assertStringContainsString( '<atproto-comments', ob_get_clean() );

		ob_start();
		include $template;
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_comments_template_kept_in_after_content_mode() {
		$this->set_setting( 'position', 'after_content' );
		$this->view( $this->post_id );
		$this->assertSame( '/theme/comments.php', apply_filters( 'comments_template', '/theme/comments.php' ) );
	}

	public function test_comments_template_kept_without_link() {
		$this->view( self::factory()->post->create() );
		$this->assertSame( '/theme/comments.php', apply_filters( 'comments_template', '/theme/comments.php' ) );
	}

	public function test_comments_template_after_content_fallback_prints_nothing() {
		$this->close_comments();
		$this->view( $this->post_id );
		$this->assertStringContainsString( '<atproto-comments', apply_filters( 'the_content', 'Body' ) );
		ob_start();
		include apply_filters( 'comments_template', '/theme/comments.php' );
		$this->assertSame( '', ob_get_clean() );
	}

	// Blocks.

	public function test_comments_block_replaced_in_replace_mode() {
		$this->view( $this->post_id );
		$out = apply_filters( 'render_block_core/comments', '<div class="wp-block-comments">native</div>', array(), null );
		$this->assertStringStartsWith( '<atproto-comments', $out );
		$this->assertSame( '', apply_filters( 'render_block_core/comments', '<div>native</div>', array(), null ) );
	}

	public function test_legacy_comments_block_keeps_rendered_component() {
		$this->view( $this->post_id );
		$inner = '<div class="wp-block-post-comments">' . Display::render() . '</div>';
		$this->assertSame( $inner, apply_filters( 'render_block_core/post-comments', $inner, array(), null ) );
		$this->assertSame( $inner, apply_filters( 'render_block_core/comments', $inner, array(), null ) );
	}

	public function test_comments_form_block_removed_in_replace_mode() {
		$this->view( $this->post_id );
		$this->assertSame( '', apply_filters( 'render_block_core/post-comments-form', '<form>native</form>', array(), null ) );
	}

	public function test_blocks_untouched_in_after_content_mode() {
		$this->set_setting( 'position', 'after_content' );
		$this->view( $this->post_id );
		$this->assertSame( '<div>native</div>', apply_filters( 'render_block_core/comments', '<div>native</div>', array(), null ) );
		$this->assertSame( '<form>native</form>', apply_filters( 'render_block_core/post-comments-form', '<form>native</form>', array(), null ) );
	}

	public function test_blocks_untouched_without_link() {
		$this->view( self::factory()->post->create() );
		$this->assertSame( '<div>native</div>', apply_filters( 'render_block_core/comments', '<div>native</div>', array(), null ) );
		$this->assertSame( '<form>native</form>', apply_filters( 'render_block_core/post-comments-form', '<form>native</form>', array(), null ) );
	}

	// Assets.

	public function test_assets_enqueued_only_on_linked_posts() {
		$this->go_to( home_url( '/' ) );
		Display::enqueue_assets();
		$this->assertFalse( wp_style_is( 'atproto-comments', 'enqueued' ) );

		$this->go_to( get_permalink( self::factory()->post->create() ) );
		Display::enqueue_assets();
		$this->assertFalse( wp_style_is( 'atproto-comments', 'enqueued' ) );

		$this->go_to( get_permalink( $this->post_id ) );
		Display::enqueue_assets();
		$this->assertTrue( wp_style_is( 'atproto-comments', 'enqueued' ) );
		$this->assertStringEndsWith( 'assets/vendor/atproto-comments/atproto-comments.css', wp_styles()->registered['atproto-comments']->src );
		wp_dequeue_style( 'atproto-comments' );
	}
}
