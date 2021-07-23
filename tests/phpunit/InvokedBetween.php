<?php

namespace GrowthExperiments\Tests;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\Invocation;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;

/**
 * Invocation definition for expects() that checks invocation count against a range.
 */
class InvokedBetween extends InvocationOrder {

	/** @var int */
	private $min;

	/** @var int */
	private $max;

	/**
	 * @param int $min Minimum required invocation count
	 * @param int $max Maximum allowed invocation count
	 */
	public function __construct( $min, $max ) {
		$this->min = $min;
		$this->max = $max;
	}

	/** @inheritDoc */
	public function toString(): string {
		return "invoked between $this->min and $this->max times";
	}

	/** @inheritDoc */
	public function verify(): void {
		$count = $this->getInvocationCount();
		if ( $count < $this->min || $count > $this->max ) {
			throw new ExpectationFailedException(
				"Expected to be invoked between $this->min and $this->max times,"
				. " but it occurred $count time(s)."
			);
		}
	}

	/** @inheritDoc */
	public function matches( Invocation $invocation ): bool {
		return true;
	}

	/** @inheritDoc */
	protected function invokedDo( Invocation $invocation ): void {
	}

}
