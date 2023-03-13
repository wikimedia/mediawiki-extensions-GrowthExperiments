<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\ChangeMentor;

/**
 * Test-specific version of ChangeMentor class
 *
 * This class overrides ChangeMentor::log and ChangeMentor::notify to no-ops, as they cannot be
 * reasonably unit-tested (due to direct construction of ManualLogEntry and direct call of
 * EchoEvent::create respectively).
 */
class ChangeMentorForTests extends ChangeMentor {
	/** @inheritDoc */
	protected function log( string $reason, bool $forceBot ) {
		// no-op
	}

	/** @inheritDoc */
	protected function notify( string $reason ) {
		// no-op
	}
}
