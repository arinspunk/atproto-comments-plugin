<?php
/**
 * Tests for the data passed to the block editor panel.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Account;
use AtprotoComments\Post_Meta;
use AtprotoComments\Settings;

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
				'accountReady'    => true,
				'accountDid'      => '',
				'canPublish'      => true,
				'canDelete'       => true,
				'settingsUrl'     => admin_url( 'options-general.php?page=atproto-comments' ),
				'appview'         => 'https://public.api.bsky.app',
				'postUrlTemplate' => 'https://bsky.app/profile/{did}/post/{rkey}',
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

		$data = Post_Meta::editor_data( $own );
		$this->assertFalse( $data['canPublish'] );
		$this->assertFalse( $data['canDelete'] );
	}

	public function test_editor_can_delete() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertTrue( Post_Meta::editor_data( $this->post )['canDelete'] );
	}

	public function test_account_did_once_resolved() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( '', Post_Meta::editor_data( $this->post )['accountDid'] );

		Account::remember_did( 'did:plc:abc234567abcdefghijklmno' );

		$this->assertSame( 'did:plc:abc234567abcdefghijklmno', Post_Meta::editor_data( $this->post )['accountDid'] );
	}

	public function test_appview_from_settings() {
		update_option( Settings::OPTION, array( 'appview' => 'https://appview.example' ) );

		$this->assertSame( 'https://appview.example', Post_Meta::editor_data( $this->post )['appview'] );
	}

	/**
	 * Values of the post URL filter and the resulting template.
	 *
	 * @return array
	 */
	public function post_url_filters() {
		return array(
			'other app'  => array( 'https://app.example/profile/{did}/post/{rkey}', 'https://app.example/profile/{did}/post/{rkey}' ),
			'http'       => array( 'http://app.example/{did}/{rkey}', 'https://bsky.app/profile/{did}/post/{rkey}' ),
			'javascript' => array( 'javascript:alert(1)', 'https://bsky.app/profile/{did}/post/{rkey}' ),
			'not string' => array( false, 'https://bsky.app/profile/{did}/post/{rkey}' ),
		);
	}

	/**
	 * The filter changes the template; invalid values fall back to bsky.app.
	 *
	 * @dataProvider post_url_filters
	 *
	 * @param mixed  $value    Filter result.
	 * @param string $expected Template.
	 */
	public function test_post_url_filter( $value, $expected ) {
		add_filter(
			'atproto_comments_post_url',
			static function () use ( $value ) {
				return $value;
			}
		);

		$this->assertSame( $expected, Post_Meta::editor_data( $this->post )['postUrlTemplate'] );
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

		$this->assertStringContainsString( 'window.atprotoCommentsEditor = {"accountReady":true,"accountDid":"","canPublish":true,"canDelete":true,', $before );
		$this->assertStringNotContainsString( 'usoritual.com', $before );
		$this->assertStringNotContainsString( self::PASSWORD, $before );
		$this->assertStringNotContainsString( Account::get()['password'], $before );

		set_current_screen( 'front' );
	}
}
