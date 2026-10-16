<?php
/**
 * Tests for Deadline.
 *
 * @package AtprotoComments
 */

use AtprotoComments\Deadline;

/**
 * Global time budget with a simulated clock.
 */
class Deadline_Test extends WP_UnitTestCase {

	/**
	 * Simulated time.
	 *
	 * @var float
	 */
	private $now = 1000.0;

	/**
	 * Clock that returns the simulated time.
	 *
	 * @return callable
	 */
	private function clock() {
		return function () {
			return $this->now;
		};
	}

	public function test_default_budget_is_25_seconds() {
		$deadline = new Deadline( Deadline::DEFAULT_SECONDS, $this->clock() );
		$this->assertSame( 25.0, $deadline->remaining() );

		$this->now += 10;
		$this->assertSame( 15.0, $deadline->remaining() );
	}

	public function test_timeout_is_capped_by_time_left() {
		$deadline = new Deadline( 25, $this->clock() );
		$this->assertSame( 10.0, $deadline->timeout( 10 ) );

		$this->now += 22;
		$this->assertSame( 3.0, $deadline->timeout( 10 ) );
		$this->assertSame( 3.0, $deadline->timeout( 5 ) );
	}

	public function test_remaining_is_never_negative() {
		$deadline   = new Deadline( 25, $this->clock() );
		$this->now += 40;
		$this->assertSame( 0.0, $deadline->remaining() );
		$this->assertSame( 0.0, $deadline->timeout( 10 ) );
	}

	public function test_has() {
		$deadline = new Deadline( 25, $this->clock() );
		$this->assertTrue( $deadline->has( 12 ) );

		$this->now += 15;
		$this->assertFalse( $deadline->has( 12 ) );
		$this->assertTrue( $deadline->has( 10 ) );
		$this->assertTrue( $deadline->has( Deadline::MIN_REQUEST ) );

		$this->now += 8.5;
		$this->assertFalse( $deadline->has( Deadline::MIN_REQUEST ) );
	}

	public function test_real_clock() {
		$deadline = new Deadline();
		$this->assertGreaterThan( 24, $deadline->remaining() );
		$this->assertLessThanOrEqual( 25, $deadline->remaining() );
	}
}
