<?php

namespace GrowthExperiments\Tests;

use GrowthExperiments\Mentorship\ChangeMentor;
use MediaWiki\User\UserIdentity;

/**
 * Test-specific version of ChangeMentor class
 *
 * This class overrides ChangeMentor::log and ChangeMentor::notify to no-ops, as they cannot be
 * reasonably unit-tested (due to direct construction of ManualLogEntry and direct call of
 * Event::create respectively).
 *
 * It also makes ChangeMentor consider Homepage enabled for everyone.
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

	/** @inheritDoc */
	protected function isMentorshipEnabledForUser( UserIdentity $user ): bool {
		return true;
	}
}
