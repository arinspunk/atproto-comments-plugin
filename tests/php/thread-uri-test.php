<?php
/**
 * Tests for Thread_Uri.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Thread_Uri;

/**
 * Runs the shared fixture through the PHP implementation.
 */
class Thread_Uri_Test extends WP_UnitTestCase {

	/**
	 * Fixture cases.
	 *
	 * @return array
	 */
	public function fixture_cases() {
		$data  = json_decode( file_get_contents( dirname( __DIR__ ) . '/fixtures/thread-uris.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$cases = array();
		foreach ( $data['cases'] as $case ) {
			$cases[ $case['name'] ] = array( $case['input'], $case['expected'] );
		}
		return $cases;
	}

	/**
	 * Normalize matches the fixture.
	 *
	 * @dataProvider fixture_cases
	 *
	 * @param string $input    Input.
	 * @param array  $expected Expected result.
	 */
	public function test_normalize( $input, $expected ) {
		$this->assertSame( $expected, Thread_Uri::normalize( $input ) );
	}

	/**
	 * Every accepted non-empty URI matches the stored-value pattern, and rejected inputs don't.
	 *
	 * @dataProvider fixture_cases
	 *
	 * @param string $input    Input.
	 * @param array  $expected Expected result.
	 */
	public function test_pattern_matches_canonical_form( $input, $expected ) {
		if ( $expected['ok'] ) {
			$this->assertSame( 1, preg_match( '#' . Thread_Uri::PATTERN . '#', $expected['uri'] ) );
			$this->assertSame( '' !== $expected['uri'], Thread_Uri::is_canonical( $expected['uri'] ) );
		} else {
			$this->assertSame( 0, preg_match( '#' . Thread_Uri::PATTERN . '#', $input ) );
			$this->assertFalse( Thread_Uri::is_canonical( $input ) );
		}
	}
}
