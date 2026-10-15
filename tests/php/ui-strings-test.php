<?php
/**
 * Tests for UI_Strings.
 *
 * @package AtprotoComments
 */

use AtprotoComments\UI_Strings;

/**
 * Translatable strings passed to the component.
 */
class UI_Strings_Test extends WP_UnitTestCase {

	/**
	 * Keys of the component dictionary (atproto-comments 1.1.0, src/i18n.ts).
	 */
	const COMPONENT_KEYS = array( 'reply', 'replyTo', 'comment', 'otherApp', 'inputPlaceholder', 'error', 'now', 'minutes', 'hours', 'days', 'months', 'viewMore', 'invalidDomain' );

	public function test_all_returns_exactly_the_component_keys() {
		$keys     = array_keys( UI_Strings::all() );
		$expected = self::COMPONENT_KEYS;
		sort( $keys );
		sort( $expected );
		$this->assertSame( $expected, $keys );
		$this->assertCount( 13, $keys );
	}

	public function test_source_matches_untranslated_strings() {
		$this->assertSame( UI_Strings::SOURCE, UI_Strings::all() );
	}

	public function test_reply_to_keeps_name_placeholder() {
		$this->assertStringContainsString( '{name}', UI_Strings::all()['replyTo'] );
	}

	public function test_translated_is_empty_without_translation() {
		$this->assertSame( array(), UI_Strings::translated() );
	}

	public function test_translated_returns_only_translated_keys() {
		$filter = static function ( $translation, $text, $domain ) {
			return 'atproto-comments' === $domain && 'Comment' === $text ? 'Comentar' : $translation;
		};
		add_filter( 'gettext', $filter, 10, 3 );
		$this->assertSame( array( 'comment' => 'Comentar' ), UI_Strings::translated() );
		remove_filter( 'gettext', $filter, 10 );
	}

	public function test_translated_includes_contextual_strings() {
		$filter = static function ( $translation, $text, $context, $domain ) {
			return 'atproto-comments' === $domain && 'mo' === $text ? 'mes' : $translation;
		};
		add_filter( 'gettext_with_context', $filter, 10, 4 );
		$this->assertSame( array( 'months' => 'mes' ), UI_Strings::translated() );
		remove_filter( 'gettext_with_context', $filter, 10 );
	}
}
