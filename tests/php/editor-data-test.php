<?php
/**
 * Tests for the data passed to the block editor panel.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;
use AtprotoComments\Post_Meta;

/**
 * window.atprotoCommentsEditor.
 */
class Editor_Data_Test extends WP_UnitTestCase {

	const PASSWORD = 'abcd-efgh-ijkl-mnop';

	/**
	 * Post of another author.
	 *
	 * @var WP_Post
	 */
	private $post;

	public function set_up() {
		parent::set_up();
		update_option(
			Account::OPTION,
			array(
				'identifier'   => 'usoritual.com',
				'app_password' => self::PASSWORD,
			)
		);
		$this->post = get_post( self::factory()->post->create( array( 'post_author' => self::factory()->user->create( array( 'role' => 'author' ) ) ) ) );
	}

	public function tear_down() {
		delete_option( Account::OPTION );
		parent::tear_down();
	}

	public function test_administrator() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame(
			array(
				'accountReady' => true,
				'canPublish'   => true,
				'settingsUrl'  => admin_url( 'options-general.php?page=atproto-comments' ),
			),
			Post_Meta::editor_data( $this->post )
		);
	}

	public function test_editor_has_no_settings_url() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$data = Post_Meta::editor_data( $this->post );
		$this->assertTrue( $data['canPublish'] );
		$this->assertNull( $data['settingsUrl'] );
	}

	public function test_author_cannot_publish() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$own = get_post( self::factory()->post->create( array( 'post_author' => $author ) ) );

		$this->assertFalse( Post_Meta::editor_data( $own )['canPublish'] );
	}

	public function test_filter_changes_can_publish() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$own = get_post( self::factory()->post->create( array( 'post_author' => $author ) ) );
		add_filter(
			'atproto_comments_publish_capability',
			static function () {
				return 'publish_posts';
			}
		);

		$this->assertTrue( Post_Meta::editor_data( $own )['canPublish'] );
	}

	public function test_account_not_ready() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		delete_option( Account::OPTION );

		$this->assertFalse( Post_Meta::editor_data( $this->post )['accountReady'] );
	}

	public function test_inline_script_without_credentials() {
		if ( ! is_readable( ATPROTO_COMMENTS_DIR . 'build/editor.asset.php' ) ) {
			$this->markTestSkipped( 'Run npm run build first.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'post' );
		$GLOBALS['post'] = $this->post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		Post_Meta::enqueue_editor_assets();
		$before = implode( "\n", (array) wp_scripts()->get_data( 'atproto-comments-editor', 'before' ) );

		$this->assertStringContainsString( 'window.atprotoCommentsEditor = {"accountReady":true,"canPublish":true,', $before );
		$this->assertStringNotContainsString( 'usoritual.com', $before );
		$this->assertStringNotContainsString( self::PASSWORD, $before );
		$this->assertStringNotContainsString( Account::get()['password'], $before );

		set_current_screen( 'front' );
	}
}
