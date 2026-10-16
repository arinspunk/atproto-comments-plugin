<?php
/**
 * Global time budget of a request that makes several network calls.
 *
 * @package AtprotoComments
 */

namespace AtprotoComments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A deadline measured from its creation. Each network call uses the smaller
 * of its own timeout and the time left.
 */
final class Deadline {

	/**
	 * Default budget, in seconds, below the usual 30 s max_execution_time.
	 */
	const DEFAULT_SECONDS = 25;

	/**
	 * Below this many seconds left, a request is not started.
	 */
	const MIN_REQUEST = 2;

	/**
	 * Clock returning the current time in seconds.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Time at which the deadline expires.
	 *
	 * @var float
	 */
	private $expires;

	/**
	 * Starts the deadline.
	 *
	 * @param float         $seconds Budget in seconds.
	 * @param callable|null $clock   Clock returning seconds as a float. Tests
	 *                               pass a simulated one; defaults to microtime.
	 */
	public function __construct( $seconds = self::DEFAULT_SECONDS, $clock = null ) {
		$this->clock   = is_callable( $clock ) ? $clock : static function () {
			return microtime( true );
		};
		$this->expires = $this->now() + (float) $seconds;
	}

	/**
	 * Seconds left, never negative.
	 *
	 * @return float
	 */
	public function remaining() {
		return max( 0.0, $this->expires - $this->now() );
	}

	/**
	 * Timeout for a call: the smaller of `$max` and the time left.
	 *
	 * @param float $max Call timeout in seconds.
	 * @return float
	 */
	public function timeout( $max ) {
		return min( (float) $max, $this->remaining() );
	}

	/**
	 * Whether at least `$seconds` are left.
	 *
	 * @param float $seconds Seconds.
	 * @return bool
	 */
	public function has( $seconds ) {
		return $this->remaining() >= (float) $seconds;
	}

	/**
	 * Error returned instead of a request when too little time is left.
	 *
	 * @return \WP_Error
	 */
	public static function error() {
		return new \WP_Error(
			'atproto_comments_timeout',
			__( 'There was not enough time left to contact the AT Protocol server. Try again.', 'atproto-comments' ),
			array( 'status' => 502 )
		);
	}

	/**
	 * Current time from the clock.
	 *
	 * @return float
	 */
	private function now() {
		return (float) call_user_func( $this->clock );
	}
}
