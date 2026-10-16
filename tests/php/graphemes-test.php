<?php
/**
 * Tests for Graphemes with each counting method.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Graphemes;

/**
 * Counting and cutting with ext-intl, PCRE2 \X and the fallback pattern.
 */
class Graphemes_Test extends WP_UnitTestCase {

	public function tear_down() {
		Graphemes::force( null );
		parent::tear_down();
	}

	/**
	 * Texts and their number of graphemes, as ICU counts them.
	 *
	 * @return array
	 */
	public function texts() {
		return array(
			'ascii'              => array( 'Nueva entrada', 13 ),
			'accents'            => array( 'Café ñandú', 10 ),
			'combining accent'   => array( "e\u{0301}", 1 ),
			'emoji'              => array( '😀', 1 ),
			'consecutive emoji'  => array( '😀😀😀', 3 ),
			'zwj emoji'          => array( '👩‍💻', 1 ),
			'consecutive zwj'    => array( '👩‍💻👩‍💻', 2 ),
			'family'             => array( '👨‍👩‍👧‍👦', 1 ),
			'skin tone'          => array( '👍🏽👍🏽', 2 ),
			'variation selector' => array( '❤️❤️', 2 ),
			'flags'              => array( '🇪🇸🇬🇧', 2 ),
			'tag flag'           => array( "\u{1F3F4}\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F}", 1 ),
			'keycap'             => array( "1\u{FE0F}\u{20E3}", 1 ),
			'crlf'               => array( "a\r\nb", 3 ),
			'mixed'              => array( 'Hola 👋🏽 #wordpress 🇬🇱', 19 ),
		);
	}

	/**
	 * Methods available on this host.
	 *
	 * @return string[]
	 */
	private function methods() {
		$methods = array( 'fallback' );
		if ( function_exists( 'grapheme_strlen' ) ) {
			$methods[] = 'intl';
		}
		if ( Graphemes::pcre_is_reliable() ) {
			$methods[] = 'pcre';
		}
		return $methods;
	}

	/**
	 * Every available method gives the same count as ICU.
	 *
	 * @dataProvider texts
	 *
	 * @param string $text     Text.
	 * @param int    $expected Graphemes.
	 */
	public function test_count( $text, $expected ) {
		foreach ( $this->methods() as $method ) {
			Graphemes::force( $method );
			$this->assertSame( $expected, Graphemes::count( $text ), $method );
		}
	}

	public function test_300_and_301_consecutive_emoji() {
		foreach ( $this->methods() as $method ) {
			Graphemes::force( $method );
			$this->assertSame( 300, Graphemes::count( str_repeat( '😀', 300 ) ), $method );
			$this->assertSame( 301, Graphemes::count( str_repeat( '😀', 301 ) ), $method );
			$this->assertSame( 300, Graphemes::count( str_repeat( 'a', 299 ) . '👩‍💻' ), $method );
		}
	}

	public function test_truncate() {
		foreach ( $this->methods() as $method ) {
			Graphemes::force( $method );
			$this->assertSame( '😀😀', Graphemes::truncate( '😀😀😀', 2 ), $method );
			$this->assertSame( 'ab👩‍💻', Graphemes::truncate( 'ab👩‍💻👩‍💻', 3 ), $method );
			$this->assertSame( 'short', Graphemes::truncate( 'short', 300 ), $method );
		}
	}

	public function test_invalid_utf8() {
		foreach ( $this->methods() as $method ) {
			Graphemes::force( $method );
			$this->assertNull( Graphemes::count( "caf\xC3" ), $method );
			$this->assertSame( '', Graphemes::truncate( "caf\xC3", 2 ), $method );
		}
	}

	public function test_detection() {
		Graphemes::force( null );
		if ( function_exists( 'grapheme_strlen' ) ) {
			$this->assertSame( 'intl', Graphemes::method() );
		} else {
			$this->assertSame( Graphemes::pcre_is_reliable() ? 'pcre' : 'fallback', Graphemes::method() );
		}
	}

	/**
	 * `\X` is not trusted blindly: PCRE2 10.42 and earlier join consecutive
	 * emoji into one cluster.
	 */
	public function test_pcre_that_joins_emoji_is_not_reliable() {
		if ( 2 === preg_match_all( '/\X/u', '😀😀' ) ) {
			$this->markTestSkipped( 'This PCRE2 (' . PCRE_VERSION . ') splits consecutive emoji.' );
		}
		$this->assertFalse( Graphemes::pcre_is_reliable(), PCRE_VERSION );
	}
}
